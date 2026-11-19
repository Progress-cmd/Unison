<?php

namespace App\Library\Service;

use App\Library\Entity\Album;
use App\Library\Entity\Track;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ContentRemover
{
    public function __construct(
        private EntityManagerInterface $em,
        private TrackRepository $trackRepository,
        private AlbumRepository $albumRepository,
        private ArtistRepository $artistRepository,
        private Filesystem $filesystem,
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private string $musicDir,
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {}

    public function removeTrack(Track $track): void
    {
        $fileName = $track->getFileName();
        $cover = $track->getCover();

        $this->em->remove($track);
        $this->em->flush();

        $this->filesystem->remove($this->musicDir . '/' . $fileName);

        $this->removeCoverIfUnused($cover);

        $this->artistRepository->removeOrphans();
    }

    public function removeAlbum(Album $album): void
    {
        $cover = $album->getCover();

        foreach ($album->getTracks()->toArray() as $track) {
            $this->removeTrack($track);
        }

        $this->em->remove($album);
        $this->em->flush();

        $this->removeCoverIfUnused($cover);

        $this->artistRepository->removeOrphans();
    }

    private function removeCoverIfUnused(?string $cover): void
    {
        if (null !== $cover && str_starts_with($cover, '/covers/') && 0 === ($this->trackRepository->count(['cover' => $cover]) + $this->albumRepository->count(['cover' => $cover]))) {
            $this->filesystem->remove($this->publicDir . $cover);
        }
    }
}