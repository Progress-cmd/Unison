<?php

namespace App\Library\Repository;

use App\Library\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findAllAlbum(?int $limit = null): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.artists', 'ar')
            ->addSelect('ar')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
        ;

        // Without a limit, nothing to cut: a plain result is enough.
        if (null === $limit) {
            return $query->getResult();
        }

        return (new OffsetPaginator())->paginate($query, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    /**
     * Albums whose title or one of whose artists contains $search, with their artists.
     */
    public function search(string $search, int $limit = 10): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.artists', 'ar')
            ->addSelect('ar')
            // A second join only to filter: the first one keeps all the album's artists.
            ->leftJoin('a.artists', 'searchArtist')
            ->andWhere('a.title LIKE :search OR searchArtist.name LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->orderBy('a.title', 'ASC')
            ->getQuery()
        ;

        return (new OffsetPaginator())->paginate($query, Window::fromPageNumberAndSize(1, $limit))->getItems();
    }

    public function findExistingSourceUrls(array $urls): array
    {
        if (!$urls) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->select('a.sourceUrl')
            ->andWhere('a.sourceUrl IN (:urls)')
            ->setParameter('urls', $urls)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }
}
