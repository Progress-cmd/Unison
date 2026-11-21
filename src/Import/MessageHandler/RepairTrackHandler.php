<?php

namespace App\Import\MessageHandler;

use App\Import\Message\RepairTrack;
use App\Import\Service\CoverDownloader;
use App\Import\Service\YtDlpClient;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// Downloads again what a track lacks: its audio file, its cover. The rest (title, playlists,
// favourites, listens) stays as it is. Its album gets the new cover when it had the same broken one.
#[AsMessageHandler]
final class RepairTrackHandler
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly YtDlpClient $ytDlpClient,
        private readonly CoverDownloader $coverDownloader,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {}

    public function __invoke(RepairTrack $message): void
    {
        $track = $this->trackRepository->find($message->trackId);

        // Deleted in the meantime: nothing to repair.
        if (null === $track) {
            return;
        }

        if (!is_file($this->musicDir.'/'.$track->getFileName())) {
            $track->setFileName($this->ytDlpClient->downloadAudio($track->getSourceUrl()));
        }

        $cover = $track->getCover();
        $videoId = CoverDownloader::videoIdFrom($track->getSourceUrl());

        if (null !== $videoId && !$this->isLocalFile($cover)) {
            $local = $this->coverDownloader->localize(str_starts_with((string) $cover, 'http') ? $cover : null, $videoId);

            if (null !== $local) {
                $track->setCover($local);

                $album = $track->getAlbum();

                if (null !== $album && ($album->getCover() === $cover || !$this->isLocalFile($album->getCover()))) {
                    $album->setCover($local);
                }
            }
        }

        $this->em->flush();
    }

    private function isLocalFile(?string $cover): bool
    {
        return null !== $cover && str_starts_with($cover, '/covers/') && is_file($this->publicDir.$cover);
    }
}
