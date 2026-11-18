<?php

namespace App\Admin\Entity;

use App\Admin\Enum\ErrorSource;
use App\Admin\Repository\ErrorReportRepository;
use App\Import\Entity\ImportJob;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ErrorReportRepository::class)]
class ErrorReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ErrorSource::class, length: 20)]
    private ?ErrorSource $source = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $context = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ImportJob $importJob = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(ErrorSource $source, string $message, ?string $context, ?ImportJob $importJob)
    {
        $this->source = $source;
        $this->message = $message;
        $this->context = $context;
        $this->importJob = $importJob;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?ErrorSource
    {
        return $this->source;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getImportJob(): ?ImportJob
    {
        return $this->importJob;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }
}
