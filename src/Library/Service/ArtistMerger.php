<?php

namespace App\Library\Service;

use App\Library\Entity\Artist;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ArtistMerger
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function merge(Artist $source, Artist $target): void
    {
        foreach ($source->getTracks()->toArray() as $track) {
            $track->removeArtist($source);
            $track->addArtist($target);
        }

        foreach ($source->getAlbums()->toArray() as $album) {
            $album->removeArtist($source);
            $album->addArtist($target);
        }

        if (null === $target->getImage()) {
            $target->setImage($source->getImage());
        }

        $this->em->remove($source);
        $this->em->flush();
    }
}
