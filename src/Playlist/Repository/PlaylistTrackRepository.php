<?php

namespace App\Playlist\Repository;

use App\Playlist\Entity\Playlist;
use App\Playlist\Entity\PlaylistTrack;
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
}
