<?php

namespace App\Library\Entity;

use App\Account\Entity\User;
use App\Library\Repository\FavoriteTrackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriteTrackRepository::class)]
#[ORM\UniqueConstraint(fields: ['user', 'track'])]
class FavoriteTrack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Track $track = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $addedAt = null;

    public function __construct(User $user, Track $track)
    {
        $this->addedAt = new \DateTimeImmutable();
        $this->user = $user;
        $this->track = $track;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getTrack(): ?Track
    {
        return $this->track;
    }

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }
}
