<?php

namespace App\Account\Service;

use App\Account\Entity\User;
use App\Account\Repository\ResetPasswordRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

// Emails a "choose your password" link. Nobody but the person ever sees or types their password.
// Three occasions, each with its own words in emails/password_link.html.twig.
final class PasswordLinkSender
{
    private const SUBJECTS = [
        'welcome' => 'Bienvenue sur Unison',
        'reset' => 'Unison : choisis un nouveau mot de passe',
        'forgotten' => 'Unison : mot de passe oublié',
    ];

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        // The public address of the site. The links of the emails always start with it, whatever
        // address the request came from: an account created from the admin address (reachable
        // through Netbird only) must get a link its owner can open, and a forged Host header
        // can't send someone to another site.
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $publicUrl,
    ) {
    }

    // An admin created the account.
    public function sendWelcome(User $user): void
    {
        $this->sendFresh($user, 'welcome');
    }

    // An admin reset the password.
    public function sendReset(User $user): void
    {
        $this->sendFresh($user, 'reset');
    }

    /**
     * The person asked from the login page ("Mot de passe oublié ?"). The bundle's limit stays:
     * one link per hour, so nobody can flood someone's inbox by asking again and again.
     *
     * @throws \SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException when a link left less than an hour ago
     */
    public function sendForgotten(User $user): void
    {
        $this->send($user, 'forgotten', $this->resetPasswordHelper->generateResetToken($user));
    }

    // From an admin: the older links are deleted, only the newest works. It also lifts the
    // bundle's "one link per hour" limit, which is meant for strangers, not for the admin.
    private function sendFresh(User $user, string $kind): void
    {
        $this->resetPasswordRequestRepository->removeRequests($user);

        $this->send($user, $kind, $this->resetPasswordHelper->generateResetToken($user));
    }

    /**
     * @throws TransportExceptionInterface when the email could not leave
     */
    private function send(User $user, string $kind, ResetPasswordToken $token): void
    {
        $email = (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject(self::SUBJECTS[$kind])
            ->htmlTemplate('emails/password_link.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'link' => rtrim($this->publicUrl, '/') . $this->urlGenerator->generate('app_reset_password_token', ['token' => $token->getToken()]),
                'kind' => $kind,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // The link never left: it mustn't count as sent (it would block a new request for an hour).
            $this->resetPasswordRequestRepository->removeRequests($user);

            throw $e;
        }
    }
}
