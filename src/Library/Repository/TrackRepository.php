<?php

namespace App\Library\Repository;

use App\Account\Entity\User;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Entity\FavoriteTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Track>
 */
class TrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    /**
     * One page of the whole library, newest first (library page, loaded while scrolling).
     *
     * @return WindowPage<Track>
     */
    public function paginateLatest(Window $window): WindowPage
    {
        $query = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, $window);
    }

    /**
     * The last favourites of the user (home page).
     */
    public function findFavorites(User $user, int $limit): array
    {
        return $this->paginateFavorites($user, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    /**
     * One page of the user's favourites, last added first.
     *
     * @return WindowPage<Track>
     */
    public function paginateFavorites(User $user, Window $window): WindowPage
    {
        $query = $this->createQueryBuilder('t')
            ->innerJoin(FavoriteTrack::class, 'f', 'WITH', 'f.track = t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.addedAt', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, $window);
    }

    /**
     * One page of an artist's tracks, by title.
     *
     * @return WindowPage<Track>
     */
    public function paginateByArtist(Artist $artist, Window $window): WindowPage
    {
        $query = $this->createQueryBuilder('t')
            ->innerJoin('t.artists', 'filter')
            ->andWhere('filter = :artist')
            ->setParameter('artist', $artist)
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->orderBy('t.title', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, $window);
    }

    public function findRandom(int $count, ?Track $exclude = null): array
    {
        $qb = $this->createQueryBuilder('t')->select('t.id');

        if (null !== $exclude) {
            $qb->andWhere('t.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        $trackIds = $qb->getQuery()->getSingleColumnResult();

        if (!$trackIds) {
            return [];
        }

        shuffle($trackIds);
        $trackIds = array_slice($trackIds, 0, $count);

        $tracks = $this->createQueryBuilder('t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $trackIds)
            ->getQuery()
            ->getResult()
        ;

        shuffle($tracks);

        return $tracks;
    }

    public function findByIdsInOrder(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $tracks = $this->createQueryBuilder('t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;

        $byId = [];
        foreach ($tracks as $track) {
            $byId[$track->getId()] = $track;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    public function search(string $search, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->leftJoin('t.artists', 'searchArtist')
            ->andWhere('t.title LIKE :search OR searchArtist.name LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->orderBy('t.title', 'ASC')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    public function findExistingSourceUrls(array $urls): array
    {
        if (!$urls) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->select('t.sourceUrl')
            ->andWhere('t.sourceUrl IN (:urls)')
            ->setParameter('urls', $urls)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    /**
     * Total length of the library, in seconds.
     */
    public function totalDuration(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('SUM(t.duration)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Tracks whose title was changed by TitleCleaner.
     */
    public function countCleaned(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.originalTitle IS NOT NULL')
            ->andWhere('t.title <> t.originalTitle')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Tracks added per UTC hour since that date, for LocalCalendar (admin dashboard).
     *
     * @return array<string, int> 'Y-m-d H:00:00' => count
     */
    public function countAddedPerHour(\DateTimeImmutable $since): array
    {
        return array_map('intval', $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour, COUNT(*) FROM track WHERE created_at >= :since GROUP BY hour",
            ['since' => $since->format('Y-m-d H:i:s')],
        ));
    }

    /**
     * The tracks whose cover is still a link to YouTube rather than a file on the server
     * (app:covers:localize).
     *
     * @return list<Track>
     */
    public function findWithRemoteCover(): array
    {
        return $this->remoteCover()->getQuery()->getResult();
    }

    // How many of them (admin dashboard).
    public function countRemoteCovers(): int
    {
        return (int) $this->remoteCover()->select('COUNT(x.id)')->getQuery()->getSingleScalarResult();
    }

    private function remoteCover(): QueryBuilder
    {
        return $this->createQueryBuilder('x')->andWhere('x.cover LIKE :remote')->setParameter('remote', 'http%');
    }
}
