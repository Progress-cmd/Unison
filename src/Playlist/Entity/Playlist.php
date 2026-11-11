<?php

namespace App\Playlist\Entity;

use App\Account\Entity\User;
use App\Playlist\Repository\PlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistRepository::class)]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, PlaylistTrack>
     */
    #[ORM\OneToMany(targetEntity: PlaylistTrack::class, mappedBy: 'playlist')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $playlistTracks;

    public function __construct(User $createdBy)
    {
        $this->playlistTracks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->createdBy = $createdBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, PlaylistTrack>
     */
    public function getPlaylistTracks(): Collection
    {
        return $this->playlistTracks;
    }

    public function addPlaylistTrack(PlaylistTrack $playlistTrack): static
    {
        if (!$this->playlistTracks->contains($playlistTrack)) {
            $this->playlistTracks->add($playlistTrack);
            $playlistTrack->setPlaylist($this);
        }

        return $this;
    }

    public function removePlaylistTrack(PlaylistTrack $playlistTrack): static
    {
        if ($this->playlistTracks->removeElement($playlistTrack)) {
            // set the owning side to null (unless already changed)
            if ($playlistTrack->getPlaylist() === $this) {
                $playlistTrack->setPlaylist(null);
            }
        }

        return $this;
    }
}
