<?php

namespace App\History\Controller;

use App\Account\Entity\User;
use App\History\Repository\ListenRepository;
use App\Library\Repository\FavoriteTrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HistoryController extends AbstractController
{
    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, ListenRepository $listenRepository, FavoriteTrackRepository $favoriteTrackRepository): Response
    {
        return $this->render('history/index.html.twig', [
            'listens' => $listenRepository->findRecent($user, 100),
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }
}
