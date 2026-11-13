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

   public function findTrackIds(User $user): array
   {
       return $this->createQueryBuilder('f')
           ->select('IDENTITY(f.track)')
           ->andWhere('f.user = :user')
           ->setParameter('user', $user)
           ->orderBy('f.addedAt', 'DESC')
           ->getQuery()
           ->getSingleColumnResult()
       ;
   }
}
