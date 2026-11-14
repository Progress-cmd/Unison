<?php

namespace App\Library\Controller;

use App\Account\Entity\User;
use App\Library\Entity\Album;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Playlist\Repository\PlaylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AlbumController extends AbstractController
{
    #[Route('/library/albums', name: 'app_albums')]
    public function index(AlbumRepository $albumRepository): Response
    {
        return $this->render('library/albums.html.twig', [
            'albums' => $albumRepository->findAllAlbum(),
        ]);
    }

    #[Route('/albums/{id}', name: 'app_albums_show', requirements: ['id' => '[0-9]+'])]
    public function show(Album $album, #[CurrentUser] User $user, FavoriteTrackRepository $favoriteTrackRepository, PlaylistRepository $playlistRepository): Response
    {
        return $this->render('library/album.html.twig', [
            'album' => $album,
            'tracks' => $album->getTracks(),
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }
}