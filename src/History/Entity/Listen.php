<?php

namespace App\History\Entity;

use App\Account\Entity\User;
use App\History\Repository\ListenRepository;
use App\Library\Entity\Track;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListenRepository::class)]
#[ORM\Table(name: 'listens')]
#[ORM\Index(name: 'IDX_LISTENS_USER_LISTENED_AT', fields: ['user', 'listenedAt'])]
class Listen
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
    private \DateTimeImmutable $listenedAt;

    public function __construct()
    {
        $this->listenedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTrack(): ?Track
    {
        return $this->track;
    }

    public function setTrack(?Track $track): static
    {
        $this->track = $track;

        return $this;
    }

    public function getListenedAt(): \DateTimeImmutable
    {
        return $this->listenedAt;
    }
}
