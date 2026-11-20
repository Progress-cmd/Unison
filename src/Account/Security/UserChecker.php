<?php

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Called by Symfony at every login (form and "Se souvenir de moi"), before the password is checked.
final class UserChecker implements UserCheckerInterface
{
    public const DISABLED_MESSAGE = 'Ce compte est désactivé.';

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isDisabled()) {
            // This exception's message is shown as is on the login page.
            throw new CustomUserMessageAccountStatusException(self::DISABLED_MESSAGE);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
