<?php

namespace App\Library\Repository;

use App\Library\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Artist>
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    public function findTop(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.tracks', 't')
            ->addSelect('COUNT(t.id) AS HIDDEN trackCount')
            ->groupBy('a.id')
            ->orderBy('trackCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOrCreate(string $name): Artist
    {
        $artist = $this->findOneBy(['name' => $name]);

        if (null === $artist) {
            $artist = new Artist();

            $artist->setName($name);
            $this->getEntityManager()->persist($artist);
        }

        return $artist;
    }

    public function removeOrphans(): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.tracks IS EMPTY')
            ->andWhere('a.albums IS EMPTY')
            ->getQuery()
            ->execute()
        ;
    }

    public function search(string $search, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->setMaxResults($limit)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
