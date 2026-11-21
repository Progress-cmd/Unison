<?php

namespace App\History\Repository;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
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

    /**
     * Listens per UTC hour, for LocalCalendar (admin dashboard). Everything if $since is null.
     *
     * @return array<string, int> 'Y-m-d H:00:00' => count
     */
    public function countPerHour(?\DateTimeImmutable $since = null): array
    {
        $sql = "SELECT DATE_FORMAT(listened_at, '%Y-%m-%d %H:00:00') AS hour, COUNT(*) FROM listens";
        $params = [];

        if (null !== $since) {
            $sql .= ' WHERE listened_at >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        return array_map('intval', $this->getEntityManager()->getConnection()->fetchAllKeyValue($sql.' GROUP BY hour', $params));
    }

    // Seconds of music listened since that date: the length of each track listened to.
    public function listenedSecondsSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(t.duration), 0)')
            ->innerJoin('l.track', 't')
            ->andWhere('l.listenedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * The most listened tracks, all accounts together.
     *
     * @return list<array{0: Track, listens: int}>
     */
    public function topTracks(int $limit): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('t', 'COUNT(l.id) AS listens')
            ->from(Track::class, 't')
            ->innerJoin(Listen::class, 'l', 'WITH', 'l.track = t')
            ->groupBy('t.id')
            ->orderBy('listens', 'DESC')
            ->addOrderBy('t.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The most listened artists: each listen counts for every artist of the track.
     *
     * @return list<array{0: Artist, listens: int}>
     */
    public function topArtists(int $limit): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('a', 'COUNT(l.id) AS listens')
            ->from(Artist::class, 'a')
            ->innerJoin('a.tracks', 't')
            ->innerJoin(Listen::class, 'l', 'WITH', 'l.track = t')
            ->groupBy('a.id')
            ->orderBy('listens', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Listens of each enabled account, those who listened most first (0 included).
     *
     * @return list<array{0: User, listens: int}>
     */
    public function perAccount(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('u', 'COUNT(l.id) AS listens')
            ->from(User::class, 'u')
            ->leftJoin(Listen::class, 'l', 'WITH', 'l.user = u')
            ->andWhere('u.disabledAt IS NULL')
            ->groupBy('u.id')
            ->orderBy('listens', 'DESC')
            ->addOrderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
