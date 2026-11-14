<?php

namespace App\Shared\Controller;

use App\Account\Entity\User;
use App\Library\Repository\FavoriteTrackRepository;
use App\Library\Repository\TrackRepository;
use App\Playlist\Repository\PlaylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PlaylistRepository $playlistRepository, #[CurrentUser] User $user, FavoriteTrackRepository $favoriteTrackRepository, TrackRepository $trackRepository): Response
    {
        return $this->render('shared/home.html.twig', [
            'recentPlaylists' => $playlistRepository->findBy(['createdBy' => $user], ['updatedAt' => 'DESC'], 4),
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
            'tracks' => $trackRepository->findFavorites($user, 5),
        ]);
    }
}
