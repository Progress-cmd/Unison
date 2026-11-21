<?php

namespace App\Announcement\Entity;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;

// « This account has seen this announcement »: what makes it show only once. The unique
// constraint keeps it so even when two tabs close it at the same moment.
#[ORM\Entity]
#[ORM\UniqueConstraint(fields: ['announcement', 'user'])]
class AnnouncementView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Deleting an announcement forgets who saw it: recreated, it shows again.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Announcement $announcement;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $seenAt;

    public function __construct(Announcement $announcement, User $user)
    {
        $this->announcement = $announcement;
        $this->user = $user;
        $this->seenAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnouncement(): Announcement
    {
        return $this->announcement;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }
}
