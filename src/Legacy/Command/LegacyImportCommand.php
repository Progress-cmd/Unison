<?php

namespace App\Legacy\Command;

use App\Account\Entity\User;
use App\Account\Enum\Theme;
use App\Account\Repository\UserRepository;
use App\Import\Entity\ImportJob;
use App\Import\Enum\ImportStatus;
use App\Import\Repository\ImportJobRepository;
use App\Import\Service\ImportLauncher;
use App\Legacy\LegacyDatabase;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// First half of the migration from the v1: the accounts (with their current passwords),
// then every track put in the import queue, to be downloaded again by the worker
// (cleaner titles and covers than the v1 files). Can be run again: what exists or is on its
// way is skipped, what failed is asked again.
// Then, once the downloads are done: app:legacy:link.
#[AsCommand(name: 'app:legacy:import', description: 'Reprend les comptes de la v1 et remet tous ses titres en téléchargement')]
final class LegacyImportCommand
{
    // v1 theme values → this application's.
    private const THEMES = ['systeme' => Theme::System, 'clair' => Theme::Light, 'sombre' => Theme::Dark];

    public function __construct(
        private readonly LegacyDatabase $legacyDatabase,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly TrackRepository $trackRepository,
        private readonly ImportJobRepository $importJobRepository,
        private readonly ImportLauncher $importLauncher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        // Usernames to make admin (the v1 admin was a separate « system » account).
        #[Option(description: 'Nom d\'un compte à rendre admin (répétable)')] array $admin = [],
        // To try on a few tracks first.
        #[Option(description: 'Ne met en file que ce nombre de titres')] ?int $limit = null,
    ): int {
        $legacy = $this->legacyDatabase->connect();

        $io->section('Comptes');
        $users = $this->importUsers($legacy->fetchAllAssociative('SELECT id, username, email, `password-hash` AS hash, theme, role FROM users ORDER BY id'), $admin, $io);

        if (!$users) {
            $io->error('Aucun compte repris : rien à rattacher aux titres.');

            return Command::FAILURE;
        }

        $io->section('Titres');
        $this->queueTracks($legacy->fetchAllAssociative('SELECT id, url, `added-by_id` AS added_by FROM tracks ORDER BY id'), $users, $limit, $io);

        $io->success('Le worker télécharge maintenant les titres (page Importer pour suivre). Une fois fini : app:legacy:link.');

        return Command::SUCCESS;
    }

    /**
     * @return array<int, User> v1 user id → account here
     */
    private function importUsers(array $rows, array $admins, SymfonyStyle $io): array
    {
        $users = [];

        foreach ($rows as $row) {
            // The v1 « system » admin has no email: this application logs in by email.
            if (!$row['email']) {
                $io->writeln(sprintf(' - « %s » : pas d\'e-mail, non repris', $row['username']));
                continue;
            }

            $user = $this->userRepository->findOneBy(['email' => $row['email']]);

            if ($user) {
                $io->writeln(sprintf(' - « %s » : existe déjà', $row['username']));
            } else {
                // The v1 hash is a standard PHP bcrypt hash: Symfony checks it as is, and
                // re-hashes it with its current algorithm at the next login.
                $user = (new User())
                    ->setEmail($row['email'])
                    ->setUsername($row['username'])
                    ->setPassword((string) $row['hash'])
                    ->setTheme(self::THEMES[$row['theme']] ?? Theme::System);

                if ('admin' === $row['role'] || in_array($row['username'], $admins, true)) {
                    $user->setRoles(['ROLE_ADMIN']);
                }

                $this->em->persist($user);
                $io->writeln(sprintf(' - « %s » : créé%s', $row['username'], $user->getRoles() !== ['ROLE_USER'] ? ', admin' : ''));
            }

            $users[(int) $row['id']] = $user;
        }

        $this->em->flush();

        return $users;
    }

    private function queueTracks(array $rows, array $users, ?int $limit, SymfonyStyle $io): void
    {
        // Tracks added by an account not taken over (« system »): to the first admin.
        $fallback = current(array_filter($users, fn (User $user) => in_array('ROLE_ADMIN', $user->getRoles(), true))) ?: current($users);

        $existing = array_flip($this->trackRepository->findExistingSourceUrls(array_map(
            fn (array $row) => LegacyDatabase::sourceUrl((string) LegacyDatabase::videoId($row['url'])),
            $rows,
        )));
        // A failed download (YouTube sometimes refuses a burst of them) is asked again.
        $queued = array_flip(array_map(
            fn (ImportJob $job) => $job->getUrl(),
            array_filter($this->importJobRepository->findAll(), fn (ImportJob $job) => ImportStatus::Failed !== $job->getStatus()),
        ));

        $added = 0;
        $skipped = 0;
        $unreadable = [];

        foreach ($rows as $row) {
            $videoId = LegacyDatabase::videoId($row['url']);

            if (null === $videoId) {
                $unreadable[] = $row['url'];
                continue;
            }

            $url = LegacyDatabase::sourceUrl($videoId);

            // Already in the library, or already asked by an earlier run.
            if (isset($existing[$url]) || isset($queued[$url])) {
                ++$skipped;
                continue;
            }

            if (null !== $limit && $added >= $limit) {
                break;
            }

            $job = new ImportJob($url, $users[(int) $row['added_by']] ?? $fallback);
            $this->em->persist($job);
            $this->em->flush();

            $this->importLauncher->launch($job);
            $queued[$url] = true;
            ++$added;
        }

        $io->writeln(sprintf(' - %d titres mis en file, %d déjà là ou en cours', $added, $skipped));

        if ($unreadable) {
            $io->warning(sprintf("%d liens illisibles, non repris :\n%s", count($unreadable), implode("\n", $unreadable)));
        }
    }
}
