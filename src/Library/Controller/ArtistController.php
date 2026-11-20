<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Entity\Artist;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Library\Repository\TrackRepository;
use App\Shared\Pagination\ListPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ArtistController extends AbstractController
{
    #[Route('/library/artists', name: 'app_artists')]
    public function index(ArtistRepository $artistRepository): Response
    {
        return $this->render('library/artists.html.twig', [
            'artists' => $artistRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/artists/{id}', name: 'app_artists_show', requirements: ['id' => '[0-9]+'])]
    public function show(Request $request, Artist $artist, #[CurrentUser] User $user, FavoriteTrackRepository $favoriteTrackRepository, TrackRepository $trackRepository): Response
    {
        $page = $trackRepository->paginateByArtist($artist, ListPage::window($request));

        // The next 50, while scrolling: the rows alone.
        return $this->render(ListPage::isNextRows($request) ? 'library/_track_rows.html.twig' : 'library/artist.html.twig', [
            'artist' => $artist,
            'page' => $page,
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }
}
