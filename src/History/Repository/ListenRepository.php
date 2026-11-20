<?php

namespace App\History\Repository;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\Pagination\WindowPage;
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

    /**
     * The last listens of the user (account page).
     */
    public function findRecent(User $user, int $limit): array
    {
        return $this->paginateRecent($user, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    /**
     * One page of the user's listening history, most recent first.
     *
     * @return WindowPage<Listen>
     */
    public function paginateRecent(User $user, Window $window): WindowPage
    {
        $query = $this->createQueryBuilder('l')
            ->innerJoin('l.track', 't')
            ->addSelect('t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.listenedAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, $window);
    }
}
