<?php

namespace App\Announcement\Controller;

use App\Account\Entity\User;
use App\Announcement\Entity\Announcement;
use App\Announcement\Repository\AnnouncementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

final class AnnouncementController extends AbstractController
{
    // « J'ai compris » (or Escape) in the announcement window: it won't show again.
    #[Route('/announcements/{id}/seen', name: 'app_announcement_seen', methods: ['POST'])]
    public function seen(Request $request, #[CurrentUser] User $user, Announcement $announcement, AnnouncementRepository $announcementRepository): Response
    {
        if (!$this->isCsrfTokenValid('announcement_seen', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $announcementRepository->markSeen($announcement, $user);

        // From the page (Turbo): the window goes, the page stays as it is (scroll, player).
        // The next announcement, if there is one, waits for the next page: one at a time.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return new Response('<turbo-stream action="remove" target="announcement"></turbo-stream>');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
    }
}
