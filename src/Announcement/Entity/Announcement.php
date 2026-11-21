<?php

namespace App\Announcement\Entity;

use App\Announcement\Enum\AnnouncementKind;
use App\Announcement\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// A message written in the administration, shown once to every account (AnnouncementView
// remembers who saw it).
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title;

    // Plain text: its line breaks are kept on display, never read as HTML.
    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(enumType: AnnouncementKind::class, length: 20)]
    private AnnouncementKind $kind;

    // Withdrawn (false): no longer shown to those who haven't seen it. Those who have
    // seen it don't see it again when it's put back.
    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $title, string $content, AnnouncementKind $kind)
    {
        $this->title = $title;
        $this->content = $content;
        $this->kind = $kind;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getKind(): AnnouncementKind
    {
        return $this->kind;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
