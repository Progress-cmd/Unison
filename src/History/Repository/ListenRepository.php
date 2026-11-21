<?php

namespace App\History\Repository;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Listens per UTC hour, for LocalCalendar (admin dashboard, « Mes écoutes »). Since the
     * beginning if $since is null, every account if $user is null.
     *
     * @return array<string, int> 'Y-m-d H:00:00' => count
     */
    public function countPerHour(?\DateTimeImmutable $since = null, ?User $user = null): array
    {
        $conditions = [];
        $params = [];

        if (null !== $since) {
            $conditions[] = 'listened_at >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        if (null !== $user) {
            $conditions[] = 'user_id = :user';
            $params['user'] = $user->getId();
        }

        $sql = "SELECT DATE_FORMAT(listened_at, '%Y-%m-%d %H:00:00') AS hour, COUNT(*) FROM listens"
            .($conditions ? ' WHERE '.implode(' AND ', $conditions) : '')
            .' GROUP BY hour';

        return array_map('intval', $this->getEntityManager()->getConnection()->fetchAllKeyValue($sql, $params));
    }

    // Seconds of music listened: the length of each track listened to. Since the beginning if
    // $since is null, every account if $user is null.
    public function listenedSeconds(?\DateTimeImmutable $since = null, ?User $user = null): int
    {
        return (int) $this->filtered($this->createQueryBuilder('l'), $user, $since)
            ->select('COALESCE(SUM(t.duration), 0)')
            ->innerJoin('l.track', 't')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // How many different tracks the account has listened to.
    public function countDistinctTracks(User $user): int
    {
        return (int) $this->filtered($this->createQueryBuilder('l'), $user)
            ->select('COUNT(DISTINCT IDENTITY(l.track))')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * The most listened tracks, by one account or all of them together.
     *
     * @return list<array{0: Track, listens: int}>
     */
    public function topTracks(int $limit, ?User $user = null): array
    {
        return $this->filtered($this->getEntityManager()->createQueryBuilder(), $user)
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
     * The most listened artists, by one account or all of them together: each listen counts
     * for every artist of the track.
     *
     * @return list<array{0: Artist, listens: int}>
     */
    public function topArtists(int $limit, ?User $user = null): array
    {
        return $this->filtered($this->getEntityManager()->createQueryBuilder(), $user)
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

    // The listens (alias l) of one account and since a date, when given.
    private function filtered(QueryBuilder $qb, ?User $user, ?\DateTimeImmutable $since = null): QueryBuilder
    {
        if (null !== $user) {
            $qb->andWhere('l.user = :user')->setParameter('user', $user);
        }

        if (null !== $since) {
            $qb->andWhere('l.listenedAt >= :since')->setParameter('since', $since);
        }

        return $qb;
    }
}
