<?php

namespace App\Playlist\Controller;

use App\Library\Entity\Track;
use App\Playlist\Repository\PlaylistRepository;
use App\Playlist\Repository\PlaylistTrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlaylistPickerController extends AbstractController
{
    #[Route('/tracks/{id}/playlists', name: 'app_track_playlists', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    public function index(Track $track, PlaylistRepository $playlistRepository, PlaylistTrackRepository $playlistTrackRepository): Response
    {
        return $this->render('playlist/_picker.html.twig', [
            'track' => $track,
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
            'checkedIds' => $playlistTrackRepository->findPlaylistIds($track)
        ]);
    }
}
