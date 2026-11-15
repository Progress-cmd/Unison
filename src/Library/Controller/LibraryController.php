<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Repository\TrackRepository;
use App\Library\Repository\FavoriteTrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LibraryController extends AbstractController
{
    #[Route('/library', name: 'app_library')]
    public function index(FavoriteTrackRepository $favoriteTrackRepository, #[CurrentUser] User $user, TrackRepository $trackRepository): Response
    {
        return $this->render('library/index.html.twig', [
            'tracks' => $trackRepository->findLatest(),
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }
}
