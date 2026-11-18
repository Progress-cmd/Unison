<?php

namespace App\Admin\Repository;

use App\Admin\Entity\ErrorReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ErrorReport>
 */
class ErrorReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorReport::class);
    }

    public function findUnresolved(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.resolvedAt IS NULL')
            ->orderBy('e.createdAt', 'DESC')
            ->leftJoin('e.importJob', 'j')
            ->addSelect('j')
            ->getQuery()
            ->getResult()
        ;
    }
}
