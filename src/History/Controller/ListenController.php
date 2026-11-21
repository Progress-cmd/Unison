<?php

namespace App\History\Controller;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use App\History\Repository\ListenRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ListenController extends AbstractController
{
    // Every hundred hours of listening, a cloud of butterflies (butterflies_controller.js).
    private const MILESTONE_HOURS = 100;

    #[Route('/listens', name: 'app_listen_record', methods: ['POST'])]
    public function record(Request $request, #[CurrentUser] User $user, TrackRepository $trackRepository, ListenRepository $listenRepository, EntityManagerInterface $em): Response
    {
        $data = $request->toArray();

        if (!$this->isCsrfTokenValid('listen_record', $data['_token'] ?? '')) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $track = $trackRepository->find((int) ($data['track'] ?? 0));

        if (null === $track) {
            throw $this->createNotFoundException();
        }

        $listen = new Listen();

        $listen->setUser($user);
        $listen->setTrack($track);

        $em->persist($listen);
        $em->flush();

        // This listen crosses a new hundred hours (time counted as on « Mes écoutes »): the player
        // is told, once, and celebrates.
        $hours = intdiv($listenRepository->listenedSeconds(null, $user), 3600);
        $milestone = intdiv($hours, self::MILESTONE_HOURS) * self::MILESTONE_HOURS;

        if ($milestone > $user->getListeningMilestone()) {
            $user->setListeningMilestone($milestone);
            $em->flush();

            return $this->json(['milestone' => $milestone]);
        }

        return new Response(null, 204);
    }
}
