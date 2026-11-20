<?php

namespace App\History\Controller;

use App\Account\Entity\User;
use App\History\Repository\ListenRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Shared\Pagination\ListPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HistoryController extends AbstractController
{
    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, ListenRepository $listenRepository, FavoriteTrackRepository $favoriteTrackRepository): Response
    {
        $page = $listenRepository->paginateRecent($user, ListPage::window($request));

        // The next 50, while scrolling: the rows alone.
        return $this->render(ListPage::isNextRows($request) ? 'history/_rows.html.twig' : 'history/index.html.twig', [
            'page' => $page,
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
            // Day of the last row already shown: its heading isn't repeated.
            'previousDay' => $request->query->getString('day'),
        ]);
    }
}
