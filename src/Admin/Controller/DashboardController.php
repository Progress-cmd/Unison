<?php

namespace App\Admin\Controller;

use App\Account\Repository\UserRepository;
use App\Import\Enum\ImportStatus;
use App\Import\Repository\ImportJobRepository;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(ImportJobRepository $importJobRepository, UserRepository $userRepository, AlbumRepository $albumRepository, ArtistRepository $artistRepository, TrackRepository $trackRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'nbTracks' => $trackRepository->count(),
            'nbArtists' => $artistRepository->count(),
            'nbAlbums' => $albumRepository->count(),
            'nbComptes' => $userRepository->count(),

            'nbImportPending' => $importJobRepository->count(['status' => ImportStatus::Pending]),
            'nbImportDone' => $importJobRepository->count(['status' => ImportStatus::Done]),
            'nbImportDuplicate' => $importJobRepository->count(['status' => ImportStatus::Duplicate]),
            'nbImportFailed' => $importJobRepository->count(['status' => ImportStatus::Failed]),

            'totalDuration' => $trackRepository->totalDuration(),
            'nbCleaned' => $trackRepository->countCleaned(),
        ]);
    }
}
