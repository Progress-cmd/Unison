<?php

namespace App\Account\EventListener;

use App\Account\Entity\User;
use App\Account\Security\UserChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The UserChecker only acts when someone logs in. A person already logged in when their
// account gets disabled is logged out here, on their next page.
// Default priority (0): runs after the firewall (8), so the logged-in user is known.
#[AsEventListener(event: KernelEvents::REQUEST)]
final class DisabledAccountListener
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || !$user->isDisabled()) {
            return;
        }

        // Same logout as the button: session closed, "Se souvenir de moi" cookie erased.
        $response = $this->security->logout(false) ?? new RedirectResponse('/');

        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl($this->urlGenerator->generate('app_login'));
        }

        $event->getRequest()->getSession()->getFlashBag()->add('error', UserChecker::DISABLED_MESSAGE);
        $event->setResponse($response);
    }
}
