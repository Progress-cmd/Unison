<?php

namespace App\Playlist\Controller;

use App\Account\Entity\User;
use App\Playlist\Entity\Playlist;
use App\Playlist\Form\PlaylistType;
use App\Playlist\Repository\PlaylistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PlaylistController extends AbstractController
{
    #[Route('/playlists', name: 'app_playlists')]
    public function index(Request $request, EntityManagerInterface $em, PlaylistRepository $playlistRepository, #[CurrentUser] User $user): Response
    {
        $playlist = new Playlist($user);
        $form = $this->createForm(PlaylistType::class, $playlist);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($playlist);
            $em->flush();

            return $this->redirectToRoute('app_playlists');
        }

        $mine = 'mine' === $request->getSession()->get('scope', 'all');

        $criteria = $mine ? ['createdBy' => $user] : [];

        return $this->render('playlist/index.html.twig', [
            'form' => $form,
            'playlists' => $playlistRepository->findBy($criteria, ['updatedAt' => 'DESC']),
            'mine' => $mine,
        ]);
    }

    #[Route('/playlists/{id}', name: 'app_playlist_show', requirements: ['id' => '[0-9]+'])]
    public function show(Playlist $playlist): Response
    {
        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist
        ]);
    }
}
