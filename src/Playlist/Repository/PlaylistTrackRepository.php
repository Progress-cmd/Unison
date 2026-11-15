<?php

namespace App\Playlist\Repository;

use App\Playlist\Entity\Playlist;
use App\Playlist\Entity\PlaylistTrack;
use App\Library\Entity\Track;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistTrack>
 */
class PlaylistTrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistTrack::class);
    }

    public function findPositionMax(Playlist $playlist): int
    {
        return (int) $this->createQueryBuilder('pt')
            ->select('MAX(pt.position)')
            ->andWhere('pt.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findPlaylistIds(Track $track): array
    {
        return $this->createQueryBuilder('pt')
            ->select('IDENTITY(pt.playlist)')
            ->andWhere('pt.track = :track')
            ->setParameter('track', $track)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }
}
