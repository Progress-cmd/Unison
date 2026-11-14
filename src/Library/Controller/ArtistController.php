<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Entity\Artist;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Playlist\Repository\PlaylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function show(Artist $artist, #[CurrentUser] User $user,FavoriteTrackRepository $favoriteTrackRepository, PlaylistRepository $playlistRepository, TrackRepository $trackRepository): Response
    {
        return $this->render('library/artist.html.twig', [
            'artist' => $artist,
            'tracks' => $trackRepository->findByArtist($artist),
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }
}