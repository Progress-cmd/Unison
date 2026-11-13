<?php

namespace App\Library\Repository;

use App\Account\Entity\User;
use App\Library\Entity\FavoriteTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteTrack>
 */
class FavoriteTrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteTrack::class);
    }

   public function findAllFavorite(User $user, int $page = 1): array
   {
       return $this->createQueryBuilder('f')
           ->andWhere('f.user = :user')
           ->setParameter('user', $user)
           ->orderBy('f.addedAt', 'DESC')
           ->setMaxResults(50 * $page)
           ->getQuery()
           ->getResult()
       ;
   }
}
