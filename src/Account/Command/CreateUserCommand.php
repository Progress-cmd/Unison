<?php

namespace App\Account\Command;

use App\Account\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// The first account of a new installation (then: Administration → Comptes).
#[AsCommand(name: 'app:user:create', description: 'Creates a user')]
final class CreateUserCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $email,
        #[Argument] string $username,
        #[Argument] string $password,
        #[Option(description: 'Donne accès à l\'administration')] bool $admin = false,
    ): int {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created%s.', $username, $admin ? ' (admin)' : ''));

        return 0;
    }
}
