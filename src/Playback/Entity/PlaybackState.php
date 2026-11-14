<?php

namespace App\Playback\Entity;

use App\Account\Entity\User;
use App\Playback\Repository\PlaybackStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaybackStateRepository::class)]
class PlaybackState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON)]
    private array $queue = [];

    #[ORM\Column(type: Types::JSON)]
    private array $sourceQueue = [];

    #[ORM\Column]
    private int $currentIndex = 0;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $shuffle = false;

    #[ORM\Column(length: 3)]
    private string $repeatMode = 'off';

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;


    public function __construct(User $user)
    {
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getQueue(): array
    {
        return $this->queue;
    }

    public function getSourceQueue(): array
    {
        return $this->sourceQueue;
    }

    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isShuffle(): bool
    {
        return $this->shuffle;
    }

    public function getRepeatMode(): string
    {
        return $this->repeatMode;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(array $queue, array $sourceQueue, int $currentIndex, int $position, bool $shuffle, string $repeatMode): static
    {
        $this->queue = $queue;
        $this->sourceQueue = $sourceQueue;
        $this->currentIndex = $currentIndex;
        $this->position = $position;
        $this->shuffle = $shuffle;
        $this->repeatMode = $repeatMode;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }
}
