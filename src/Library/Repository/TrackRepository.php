<?php

namespace App\Library\Repository;

use App\Account\Entity\User;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Entity\FavoriteTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Track>
 */
class TrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    public function findLatest(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findFavorites(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin(FavoriteTrack::class, 'f', 'WITH', 'f.track = t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.addedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByArtist(Artist $artist): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.artists', 'filter')
            ->andWhere('filter = :artist')
            ->setParameter('artist', $artist)
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->orderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
