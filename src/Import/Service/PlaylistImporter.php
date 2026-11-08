<?php

namespace App\Import\Service;

use App\Account\Entity\User;
use App\Library\Entity\Album;
use App\Library\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlaylistImporter
{
    public function __construct(
        private readonly YtDlpClient $ytDlpClient,
        private readonly AlbumRepository $albumRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function import(string $url, User $user): void
    {
        $playlist = $this->ytDlpClient->fetchPlaylist($url);
        $title = $this->albumTitle($playlist['title']);

        if (null === $title) {
            return;
        }

        $album = $this->albumRepository->findOneBy(['sourceUrl' => $playlist['webpage_url']]);

        if (null === $album) {
            $album = new Album();
            $album->setTitle(mb_substr($title, 0, 255));
            $album->setSourceUrl($playlist['webpage_url']);
            $album->setAddedBy($user);

            $this->em->persist($album);
            $this->em->flush();
        }
    }

    private function albumTitle(string $playlistTitle): ?string
    {
        if (1 !== preg_match('/^Album\s*[-–—]\s*(.+)$/iu', $playlistTitle, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}