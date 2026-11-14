<?php

namespace App\Library\Repository;

use App\Library\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Artist>
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

   public function findTop(int $limit): array
   {
       return $this->createQueryBuilder('a')
           ->leftJoin('a.tracks', 't')
           ->addSelect('COUNT(t.id) AS HIDDEN trackCount')
           ->groupBy('a.id')
           ->orderBy('trackCount', 'DESC')
           ->setMaxResults($limit)
           ->getQuery()
           ->getResult()
       ;
   }
}
