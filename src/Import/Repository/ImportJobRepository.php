<?php

namespace App\Import\Repository;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Import\Enum\ImportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportJob>
 */
class ImportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportJob::class);
    }

    public function findRecentFor(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.requestedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findPendingUrls(User $user, array $urls): array
    {
        if (!$urls) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->select('i.url')
            ->andWhere('i.requestedBy = :user')
            ->setParameter('user', $user)
            ->andWhere('i.status = :status')
            ->setParameter('status', ImportStatus::Pending)
            ->andWhere('i.url IN (:urls)')
            ->setParameter('urls', $urls)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }
}
