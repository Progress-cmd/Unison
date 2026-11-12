<?php

namespace App\Library\Controller;

use App\Library\Repository\TrackRepository;
use App\Playlist\Repository\PlaylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LibraryController extends AbstractController
{
    #[Route('/library', name: 'app_library')]
    public function index(TrackRepository $trackRepository, PlaylistRepository $playlistRepository): Response
    {
        return $this->render('library/index.html.twig', [
            'tracks' => $trackRepository->findLatest(),
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }
}
