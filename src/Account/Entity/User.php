<?php

namespace App\Account\Entity;

use App\Account\Enum\Theme;
use App\Account\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_USERS_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_USERS_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(enumType: Theme::class, options: ['default' => 'system'])]
    private Theme $theme = Theme::System;

    // Set by an admin: the account can't log in any more, its content stays.
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    // The last hundred hours of listening celebrated with a cloud of butterflies (100, 200…).
    #[ORM\Column(options: ['default' => 0])]
    private int $listeningMilestone = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getDisabledAt(): ?\DateTimeImmutable
    {
        return $this->disabledAt;
    }

    public function isDisabled(): bool
    {
        return null !== $this->disabledAt;
    }

    public function disable(): static
    {
        $this->disabledAt = new \DateTimeImmutable();

        return $this;
    }

    public function enable(): static
    {
        $this->disabledAt = null;

        return $this;
    }

    public function getListeningMilestone(): int
    {
        return $this->listeningMilestone;
    }

    public function setListeningMilestone(int $hours): static
    {
        $this->listeningMilestone = $hours;

        return $this;
    }
}
