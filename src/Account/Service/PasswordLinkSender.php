<?php

namespace App\Account\Service;

use App\Account\Entity\User;
use App\Account\Repository\ResetPasswordRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

// Emails a "choose your password" link: to welcome a new account, or after an admin reset.
// Nobody but the person ever sees or types their password.
final class PasswordLinkSender
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface when the email could not leave
     */
    public function send(User $user, bool $welcome): void
    {
        // Only the newest link works: the older ones are deleted. It also lifts the bundle's
        // "one request per hour" limit, which is meant for strangers, not for the admin.
        $this->resetPasswordRequestRepository->removeRequests($user);

        $token = $this->resetPasswordHelper->generateResetToken($user);

        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject($welcome ? 'Bienvenue sur Unison' : 'Unison : choisis un nouveau mot de passe')
            ->htmlTemplate('emails/password_link.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'token' => $token->getToken(),
                'welcome' => $welcome,
            ]);

        $this->mailer->send($email);
    }
}
