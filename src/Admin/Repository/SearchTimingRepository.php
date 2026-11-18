<?php

namespace App\Admin\Repository;

use App\Admin\Entity\SearchTiming;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchTiming>
 */
class SearchTimingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchTiming::class);
    }

    public function averageDuration(\DateTimeImmutable $since): ?float
    {
        return $this->createQueryBuilder('s')
            ->select('AVG(s.durationMs)')
            ->andWhere('s.failed = false')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countSince(\DateTimeImmutable $since, bool $failed): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.failed = :failed')
            ->setParameter('failed', $failed)
            ->andWhere('s.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
