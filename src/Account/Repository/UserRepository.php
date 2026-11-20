<?php

namespace App\Account\Repository;

use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Every account, with the number of tracks it imported, its playlists and its listens.
     * Each row: [0 => User, 'tracks' => …, 'playlists' => …, 'listens' => …].
     */
    public function findAllWithCounts(): array
    {
        return $this->withCounts()
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The same three numbers for a single account: ['tracks' => …, 'playlists' => …, 'listens' => …].
     */
    public function countsFor(User $user): array
    {
        $row = $this->withCounts()
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult()
        ;

        unset($row[0]);

        return $row;
    }

    private function withCounts(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->addSelect('(SELECT COUNT(t.id) FROM App\Library\Entity\Track t WHERE t.addedBy = u) AS tracks')
            ->addSelect('(SELECT COUNT(p.id) FROM App\Playlist\Entity\Playlist p WHERE p.createdBy = u) AS playlists')
            ->addSelect('(SELECT COUNT(l.id) FROM App\History\Entity\Listen l WHERE l.user = u) AS listens')
        ;
    }
}
