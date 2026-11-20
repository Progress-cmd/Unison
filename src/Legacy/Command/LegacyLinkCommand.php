<?php

namespace App\Legacy\Command;

use App\Legacy\LegacyDatabase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// Second half of the migration from the v1, once the worker has downloaded the tracks
// queued by app:legacy:import: playlists, favourites, listening history and the v1 dates.
// Can be run again as more downloads finish: what is already there is left alone (including
// the order of a playlist reorganised in the meantime), only what's missing is added.
// Plain SQL: the entities don't let their dates be set, and the v1 ones are worth keeping.
#[AsCommand(name: 'app:legacy:link', description: 'Rattache aux titres téléchargés les playlists, favoris, écoutes et dates de la v1')]
final class LegacyLinkCommand
{
    // The v1 created two playlists per account: favourites and « à écouter » (not kept).
    private const FAVORITES = 'Favorite Tracks';
    private const WAIT = 'Wait Tracks';

    private Connection $legacy;
    private Connection $db;

    public function __construct(
        private readonly LegacyDatabase $legacyDatabase,
        EntityManagerInterface $em,
    ) {
        $this->db = $em->getConnection();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->legacy = $this->legacyDatabase->connect();

        $users = $this->mapUsers();

        if (!$users) {
            $io->error('Aucun compte de la v1 trouvé ici : lancer d\'abord app:legacy:import.');

            return Command::FAILURE;
        }

        $tracks = $this->mapTracks($io);

        $this->db->transactional(function () use ($users, $tracks, $io) {
            $this->restoreTrackDates($tracks);
            $this->linkPlaylists($users, $tracks, $io);
            $this->linkListens($users, $tracks, $io);
        });

        $io->success('Rattachement fait. À relancer si d\'autres téléchargements se terminent.');

        return Command::SUCCESS;
    }

    /**
     * @return array<int, int> v1 user id → user id here, matched by email
     */
    private function mapUsers(): array
    {
        $here = $this->db->fetchAllKeyValue('SELECT email, id FROM users');
        $users = [];

        foreach ($this->legacy->fetchAllKeyValue('SELECT id, email FROM users WHERE email IS NOT NULL') as $legacyId => $email) {
            if (isset($here[$email])) {
                $users[(int) $legacyId] = (int) $here[$email];
            }
        }

        return $users;
    }

    /**
     * @return array<int, int> v1 track id → track id here, matched by YouTube id
     */
    private function mapTracks(SymfonyStyle $io): array
    {
        $here = [];

        foreach ($this->db->fetchAllKeyValue('SELECT source_url, id FROM track') as $url => $id) {
            if (null !== $videoId = LegacyDatabase::videoId($url)) {
                $here[$videoId] = (int) $id;
            }
        }

        $tracks = [];
        $missing = [];

        foreach ($this->legacy->fetchAllKeyValue('SELECT id, url FROM tracks') as $legacyId => $url) {
            $videoId = LegacyDatabase::videoId($url);

            if (null !== $videoId && isset($here[$videoId])) {
                $tracks[(int) $legacyId] = $here[$videoId];
            } else {
                $missing[] = null !== $videoId ? LegacyDatabase::sourceUrl($videoId) : $url;
            }
        }

        $io->section('Titres');
        $io->writeln(sprintf(' - %d titres de la v1 retrouvés ici, %d manquants', count($tracks), count($missing)));

        if ($missing) {
            // Why they're missing: still downloading, or failed (the reason is on the Importer page).
            // The last request of each track: a failed one may have been asked again since.
            $statuses = $this->db->fetchAllKeyValue(
                'SELECT status, COUNT(*) FROM import_job j WHERE url IN (?) AND id = (SELECT MAX(id) FROM import_job WHERE url = j.url) GROUP BY status',
                [$missing],
                [ArrayParameterType::STRING],
            );
            $asked = array_sum($statuses);

            foreach ($statuses as $status => $count) {
                $io->writeln(sprintf('   · %s : %d', ['pending' => 'en attente', 'failed' => 'en échec (relancer app:legacy:import)', 'done' => 'importés', 'duplicate' => 'déjà présents'][$status] ?? $status, $count));
            }

            if (count($missing) > $asked) {
                $io->writeln(sprintf('   · jamais demandés : %d (app:legacy:import pas encore lancé en entier ?)', count($missing) - $asked));
            }
        }

        return $tracks;
    }

    // The date a track entered the v1 rather than the day of the migration: « Derniers ajouts »
    // keeps its order.
    private function restoreTrackDates(array $tracks): void
    {
        $dates = $this->legacy->fetchAllKeyValue('SELECT id, `created-at` FROM tracks');

        foreach ($tracks as $legacyId => $id) {
            $this->db->executeStatement('UPDATE track SET created_at = ? WHERE id = ?', [$dates[$legacyId], $id]);
        }
    }

    private function linkPlaylists(array $users, array $tracks, SymfonyStyle $io): void
    {
        $io->section('Playlists');

        $names = $this->legacy->fetchAllKeyValue('SELECT id, username FROM users');
        $playlists = $this->legacy->fetchAllAssociative('SELECT id, name, `created-by_id` AS owner, `created-at` AS created_at, `updated-at` AS updated_at FROM playlists ORDER BY id');

        foreach ($playlists as $playlist) {
            $owner = $users[(int) $playlist['owner']] ?? null;

            if (null === $owner || self::WAIT === $playlist['name']) {
                continue;
            }

            // In playing order (the v1 shows them by ascending position).
            $items = $this->legacy->fetchAllAssociative(
                'SELECT track_id, `added-by_id` AS added_by, `added-at` AS added_at FROM track__playlist WHERE playlist_id = ? ORDER BY position, `added-at`',
                [$playlist['id']],
            );

            if (self::FAVORITES === $playlist['name']) {
                $added = 0;

                foreach ($items as $item) {
                    if (isset($tracks[(int) $item['track_id']])) {
                        $added += $this->db->executeStatement(
                            'INSERT IGNORE INTO favorite_track (added_at, user_id, track_id) VALUES (?, ?, ?)',
                            [$item['added_at'], $owner, $tracks[(int) $item['track_id']]],
                        );
                    }
                }

                $io->writeln(sprintf(' - favoris de %s : %d ajoutés, %d dans la v1', $names[$playlist['owner']], $added, count($items)));
                continue;
            }

            $id = $this->db->fetchOne('SELECT id FROM playlist WHERE created_by_id = ? AND name = ?', [$owner, $playlist['name']]);

            if (false === $id) {
                $this->db->insert('playlist', [
                    'name' => $playlist['name'],
                    'created_at' => $playlist['created_at'],
                    'updated_at' => $playlist['updated_at'],
                    'created_by_id' => $owner,
                ]);
                $id = $this->db->lastInsertId();
            }

            // Shown here by descending position: the first v1 track gets the highest. Computed
            // on the whole v1 list, so a track downloaded later still lands at its place.
            $added = 0;
            $count = count($items);

            foreach ($items as $index => $item) {
                if (isset($tracks[(int) $item['track_id']])) {
                    $added += $this->db->executeStatement(
                        'INSERT IGNORE INTO playlist_track (position, added_at, track_id, playlist_id, added_by_id) VALUES (?, ?, ?, ?, ?)',
                        [$count - $index, $item['added_at'], $tracks[(int) $item['track_id']], $id, $users[(int) $item['added_by']] ?? $owner],
                    );
                }
            }

            $io->writeln(sprintf(' - « %s » : %d titres ajoutés, %d dans la v1', $playlist['name'], $added, $count));
        }
    }

    private function linkListens(array $users, array $tracks, SymfonyStyle $io): void
    {
        $io->section('Écoutes');

        // Already here: same account, same track, same moment.
        $known = array_flip(array_map(
            fn (array $row) => implode('|', $row),
            $this->db->fetchAllNumeric('SELECT user_id, track_id, listened_at FROM listens'),
        ));
        $added = 0;
        $listens = $this->legacy->fetchAllNumeric('SELECT `listened-by_id`, track_id, `listened-at` FROM historical ORDER BY `listened-at`');

        foreach ($listens as [$legacyUser, $legacyTrack, $listenedAt]) {
            $user = $users[(int) $legacyUser] ?? null;
            $track = $tracks[(int) $legacyTrack] ?? null;

            if (null === $user || null === $track || isset($known[$key = implode('|', [$user, $track, $listenedAt])])) {
                continue;
            }

            $this->db->insert('listens', ['listened_at' => $listenedAt, 'user_id' => $user, 'track_id' => $track]);
            $known[$key] = true;
            ++$added;
        }

        $io->writeln(sprintf(' - %d écoutes ajoutées, %d dans la v1', $added, count($listens)));
    }
}
