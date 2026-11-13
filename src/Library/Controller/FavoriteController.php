<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Entity\Track;
use App\Library\Repository\TrackRepository;
use App\Library\Entity\FavoriteTrack;
use App\Library\Repository\FavoriteTrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class FavoriteController extends AbstractController
{
    #[Route('/favorites/toggle', name: 'app_favorite_toggle', methods: ['POST'])]
    public function index(Request $request, #[CurrentUser] User $user, TrackRepository $trackRepository, FavoriteTrackRepository $favoriteTrackRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('favorite_toggle', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $trackId = $request->request->getInt('track');

        $track = $trackRepository->find($trackId);

        if (null === $track) {
            throw $this->createNotFoundException();
        }

        $favoriteTrack = $favoriteTrackRepository->findOneBy(['track' => $track, 'user' => $user]);

        if (null === $favoriteTrack) {
            $favoriteTrack = new FavoriteTrack($user, $track);

            $em->persist($favoriteTrack);
        } else {
            $em->remove($favoriteTrack);
        }

        $em->flush();

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
    }
}
