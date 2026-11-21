<?php

namespace App\Import\Command;

use App\Import\Service\CoverDownloader;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

// Brings onto the server the covers still read from YouTube (tracks imported before
// CoverDownloader, or whose download failed). Can be run again: only the remote ones are tried.
#[AsCommand(name: 'app:covers:localize', description: 'Rapatrie sur le serveur les pochettes encore lues chez YouTube')]
final class LocalizeCoversCommand
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly CoverDownloader $coverDownloader,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(SymfonyStyle $io): int
    {
        // Remote cover → local cover, for the albums that share a track's image.
        $localized = [];
        $failed = [];

        $tracks = $this->trackRepository->findWithRemoteCover();
        $io->section(sprintf('Titres : %d pochette%s distante%s', count($tracks), count($tracks) > 1 ? 's' : '', count($tracks) > 1 ? 's' : ''));
        $io->progressStart(count($tracks));

        foreach ($tracks as $track) {
            $remote = $track->getCover();
            $videoId = CoverDownloader::videoIdFrom($track->getSourceUrl()) ?? CoverDownloader::videoIdFrom($remote);
            $local = null !== $videoId ? $this->coverDownloader->localize($remote, $videoId) : null;

            if (null === $local) {
                $failed[] = sprintf('%s (%s)', $track->getTitle(), $track->getSourceUrl());
            } else {
                $track->setCover($local);
                $localized[$remote] = $local;
                // One by one: stopped halfway, what's done stays done.
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $albums = $this->albumRepository->findWithRemoteCover();
        $io->section(sprintf('Albums : %d pochette%s distante%s', count($albums), count($albums) > 1 ? 's' : '', count($albums) > 1 ? 's' : ''));

        foreach ($albums as $album) {
            $remote = $album->getCover();
            $videoId = CoverDownloader::videoIdFrom($remote);
            $local = $localized[$remote] ?? (null !== $videoId ? $this->coverDownloader->localize($remote, $videoId) : null);

            if (null === $local) {
                $failed[] = sprintf('album %s (%s)', $album->getTitle(), $remote);
            } else {
                $album->setCover($local);
                $this->em->flush();
            }
        }

        if ($failed) {
            $io->warning(sprintf("%d pochette%s restée%s chez YouTube (vidéo supprimée, réseau) :\n%s", count($failed), count($failed) > 1 ? 's' : '', count($failed) > 1 ? 's' : '', implode("\n", $failed)));

            return Command::FAILURE;
        }

        $io->success('Toutes les pochettes sont sur le serveur.');

        return Command::SUCCESS;
    }
}
