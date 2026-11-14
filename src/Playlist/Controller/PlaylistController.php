<?php

namespace App\Playlist\Controller;

use App\Account\Entity\User;
use App\Library\Repository\FavoriteTrackRepository;
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

        return $this->render('playlist/index.html.twig', [
            'form' => $form,
            'playlists' => $playlistRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/playlists/{id}', name: 'app_playlist_show', requirements: ['id' => '[0-9]+'])]
    public function show(Playlist $playlist, FavoriteTrackRepository $favoriteTrackRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }

    #[Route('/playlists/{id}/edit', name: 'app_playlist_edit', requirements: ['id' => '[0-9]+'])]
    public function edit(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user, Playlist $playlist): Response
    {
        $owner = $playlist->getCreatedBy();

        if ($owner !== $user) {
            throw $this->createAccessDeniedException('Accès interdit');
        }

        $form = $this->createForm(PlaylistType::class, $playlist);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $playlist->setUpdatedAt(new \DateTimeImmutable);

            $em->flush();

            $this->addFlash('success', 'Playlist '.$playlist->getName().' modifiée');

            return $this->redirectToRoute('app_playlist_show', ['id' => $playlist->getId()]);
        }

        return $this->render('playlist/edit.html.twig', [
            'form' => $form,
            'playlist' => $playlist,
        ]);
    }

    #[Route('/playlists/{id}/delete', name: 'app_playlist_delete', requirements: ['id' => '[0-9]+'], methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, Playlist $playlist, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('playlist_delete', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $owner = $playlist->getCreatedBy();

        if ($owner !== $user) {
            throw $this->createAccessDeniedException('Accès interdit');
        }

        $em->remove($playlist);
        $em->flush();

        $this->addFlash('success', 'Playlist supprimée');
    
        return $this->redirectToRoute('app_playlists');
    }
}
