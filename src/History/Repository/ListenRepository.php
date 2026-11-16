<?php

namespace App\History\Repository;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Listen>
 */
class ListenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listen::class);
    }

    public function findRecent(User $user, int $limit): array
    {
        return (new OffsetPaginator())->paginate($this->createQueryBuilder('l')
            ->innerJoin('l.track', 't')
            ->addSelect('t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.listenedAt', 'DESC')
            ->getQuery(),
            Window::fromPageNumberAndSize(1, $limit))->getItems()
        ;
    }
}
