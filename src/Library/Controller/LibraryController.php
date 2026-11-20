<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Repository\FavoriteTrackRepository;
use App\Library\Repository\TrackRepository;
use App\Shared\Pagination\ListPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LibraryController extends AbstractController
{
    #[Route('/library', name: 'app_library')]
    public function index(Request $request, FavoriteTrackRepository $favoriteTrackRepository, #[CurrentUser] User $user, TrackRepository $trackRepository): Response
    {
        $page = $trackRepository->paginateLatest(ListPage::window($request));

        // The next 50, while scrolling: the rows alone.
        return $this->render(ListPage::isNextRows($request) ? 'library/_track_rows.html.twig' : 'library/index.html.twig', [
            'page' => $page,
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }
}
