<?php

namespace App\Import\Service;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Import\Message\ImportTrack;
use App\Library\Entity\Album;
use App\Library\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PlaylistImporter
{
    public function __construct(
        private readonly YtDlpClient $ytDlpClient,
        private readonly AlbumRepository $albumRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function import(string $url, User $user): string
    {
        $playlist = $this->ytDlpClient->fetchPlaylist($url);
        $title = $this->albumTitle($playlist['title']);

        $album = null;

        if (null !== $title) {
            $album = $this->findOrCreateAlbum($title, $playlist['webpage_url'], $user);
        }

        foreach ($playlist['entries'] as $index => $entry) {
            if (in_array($entry['title'], ['[Private video]', '[Deleted video]'], true)) {
                continue;
            }
            $job = new ImportJob($entry['url'], $user);

            $job->setTitle(mb_substr($entry['title'], 0, 255));
            
            $this->em->persist($job);

            $this->em->flush();

            $this->messageBus->dispatch(new ImportTrack($entry['url'], $user->getId(), $album?->getId(), null !== $album ? $index + 1 : null, $job->getId()));
        }
        return $playlist['title'];
    }

    private function albumTitle(string $playlistTitle): ?string
    {
        if (1 !== preg_match('/^Album\s*[-–—]\s*(.+)$/iu', $playlistTitle, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function findOrCreateAlbum(string $title, string $sourceUrl, User $user): Album
    {
        $album = $this->albumRepository->findOneBy(['sourceUrl' => $sourceUrl]);

        if (null === $album) {
            $album = new Album();
            $album->setTitle(mb_substr($title, 0, 255));
            $album->setSourceUrl($sourceUrl);
            $album->setAddedBy($user);

            $this->em->persist($album);
            $this->em->flush();
        }

        return $album;
    }
}