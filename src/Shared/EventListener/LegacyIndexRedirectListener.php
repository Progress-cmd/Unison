<?php

namespace App\Shared\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

// The v1's addresses went through index.php (/index.php/albums/5): bookmarks and the v1 install
// still open them, and every link of a page reached that way kept the prefix. They are sent to
// the same page without it. 308: a form posted from such a page keeps its method. Here rather
// than in Apache: behind Traefik, Apache only sees http and would redirect to it.
#[AsEventListener(priority: 64)]
final class LegacyIndexRedirectListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // A page asked for without index.php also runs index.php (FallbackResource), but its base
        // URL is then empty: only the addresses that name it are concerned.
        if (!$event->isMainRequest() || '/index.php' !== $request->getBaseUrl()) {
            return;
        }

        $query = $request->getQueryString();

        $event->setResponse(new RedirectResponse(
            $request->getSchemeAndHttpHost().$request->getPathInfo().(null !== $query ? '?'.$query : ''),
            RedirectResponse::HTTP_PERMANENTLY_REDIRECT,
        ));
    }
}
