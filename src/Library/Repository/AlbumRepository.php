<?php

namespace App\Library\Repository;

use App\Library\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findAllAlbum(?int $limit = null): array
    {
        return iterator_to_array(new Paginator($this->createQueryBuilder('a')
            ->leftJoin('a.artists', 'ar')
            ->addSelect('ar')
            ->setMaxResults($limit)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
        ));
    }
}
