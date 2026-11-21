<?php

namespace App\Announcement\Repository;

use App\Account\Entity\User;
use App\Announcement\Entity\Announcement;
use App\Announcement\Entity\AnnouncementView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * The announcement to show this account: the oldest one it hasn't seen. One at a time:
     * two in the same window, and neither gets read. Only those published since the account
     * exists: a new account doesn't get the old news.
     */
    public function findPendingFor(User $user): ?Announcement
    {
        return $this->createQueryBuilder('a')
            ->leftJoin(AnnouncementView::class, 'v', Join::WITH, 'v.announcement = a AND v.user = :user')
            ->andWhere('a.active = true')
            ->andWhere('a.createdAt >= :since')
            ->andWhere('v.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('since', $user->getCreatedAt())
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Every announcement, newest first, with how many accounts it concerns (still enabled,
     * existing when it was published) and how many of them have seen it.
     *
     * @return list<array{0: Announcement, views: int, recipients: int}>
     */
    public function findAllWithCounts(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('(SELECT COUNT(v.id) FROM '.AnnouncementView::class.' v JOIN v.user viewer WHERE v.announcement = a AND viewer.disabledAt IS NULL) AS views')
            ->addSelect('(SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.createdAt <= a.createdAt AND u.disabledAt IS NULL) AS recipients')
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Idempotent: seeing it twice (two tabs, a double click) changes nothing.
    public function markSeen(Announcement $announcement, User $user): void
    {
        $em = $this->getEntityManager();

        if (null !== $em->getRepository(AnnouncementView::class)->findOneBy(['announcement' => $announcement, 'user' => $user])) {
            return;
        }

        $em->persist(new AnnouncementView($announcement, $user));

        try {
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            // Saved by another request in between: it's seen, that's all that was asked.
        }
    }
}
