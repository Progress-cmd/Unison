<?php

namespace App\Account\Service;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Library\Entity\Album;
use App\Library\Entity\Track;
use App\Playlist\Entity\Playlist;
use App\Playlist\Entity\PlaylistTrack;
use Doctrine\ORM\EntityManagerInterface;

// Deletes an account without losing what it brought to the library.
final class AccountRemover
{
    // What the account owns, moved to the heir: entity => field pointing to the user.
    private const TRANSFERRED = [
        Track::class => 'addedBy',
        Album::class => 'addedBy',
        Playlist::class => 'createdBy',
        PlaylistTrack::class => 'addedBy',
        ImportJob::class => 'requestedBy',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    // The rest is personal and goes with the account, deleted by the database itself
    // (onDelete CASCADE): favourites, listening history, playback state, password links.
    public function remove(User $user, User $heir): void
    {
        // All or nothing: if something fails half-way, nothing has moved.
        $this->em->wrapInTransaction(function () use ($user, $heir): void {
            foreach (self::TRANSFERRED as $class => $field) {
                $this->em->createQuery(sprintf('UPDATE %s e SET e.%s = :heir WHERE e.%s = :user', $class, $field, $field))
                    ->setParameter('heir', $heir)
                    ->setParameter('user', $user)
                    ->execute();
            }

            $this->em->remove($user);
        });
    }
}
