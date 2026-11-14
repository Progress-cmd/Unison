<?php

namespace App\Shared\Controller;

use App\Account\Entity\User;
use App\Playlist\Repository\PlaylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PlaylistRepository $playlistRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('shared/home.html.twig', [
            'playlists' => $playlistRepository->findBy(['createdBy' => $user], ['updatedAt' => 'DESC'], 4),
        ]);
    }
}
