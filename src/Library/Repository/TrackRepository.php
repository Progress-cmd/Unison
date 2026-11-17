<?php

namespace App\Library\Repository;

use App\Account\Entity\User;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Entity\FavoriteTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
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

    public function findLatest(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findFavorites(User $user, ?int $limit = null): array
    {
        $query = $this->createQueryBuilder('t')
            ->innerJoin(FavoriteTrack::class, 'f', 'WITH', 'f.track = t')
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.addedAt', 'DESC')
            ->getQuery()
        ;

        // Without a limit, nothing to cut: a plain result is enough.
        if (null === $limit) {
            return $query->getResult();
        }

        return (new OffsetPaginator())->paginate($query, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    public function findByArtist(Artist $artist): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.artists', 'filter')
            ->andWhere('filter = :artist')
            ->setParameter('artist', $artist)
            ->leftJoin('t.artists', 'a')
            ->addSelect('a')
            ->orderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
}
