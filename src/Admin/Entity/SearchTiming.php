<?php

namespace App\Admin\Entity;

use App\Admin\Repository\SearchTimingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchTimingRepository::class)]
class SearchTiming
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $durationMs = null;

    #[ORM\Column]
    private ?bool $failed = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(int $durationMs, bool $failed)
    {
        $this->durationMs = $durationMs;
        $this->failed = $failed;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function isFailed(): ?bool
    {
        return $this->failed;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
