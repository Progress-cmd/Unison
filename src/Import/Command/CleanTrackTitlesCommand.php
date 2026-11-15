<?php

namespace App\Import\Command;

use App\Import\Service\TitleCleaner;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tracks:clean-titles', description: 'Cleaning Youtube titles')]
final class CleanTrackTitlesCommand
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly TitleCleaner $titleCleaner,
        private readonly EntityManagerInterface $em,
        private readonly ArtistRepository $artistRepository,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option] bool $dryRun = false,
    ): int {
        $tracks = $this->trackRepository->findAll();
        $nbCleanedTrack = 0;

        foreach ($tracks as $track) {
            $title = $track->getOriginalTitle() ?? $track->getTitle();

            $cleaned = $this->titleCleaner->clean($title);

            if ($track->getTitle() !== $cleaned->title) {
                $nbCleanedTrack +=1;
                $io->writeln($title . ' -> ' . $cleaned->title . ([] !== $cleaned->featuring)) ? (' : ' . implode(', ', $cleaned->featuring)) : '';

                if (!$dryRun) {
                    $track->setOriginalTitle($title);
                    $track->setTitle($cleaned->title);

                    foreach ($cleaned->featuring as $name) {
                        $artist = $this->artistRepository->findOrCreate($name);

                        $track->addArtist($artist);
                    }

                    $this->em->flush();
                }
            }
        }

        $io->success($nbCleanedTrack . ' titres ' . ($dryRun ? 'à nettoyer.' : 'nettoyés.'));

        return Command::SUCCESS;
    }
}
