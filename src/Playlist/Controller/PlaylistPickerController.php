<?php

namespace App\Playlist\Controller;

use App\Account\Entity\User;
use App\Library\Entity\Track;
use App\Playlist\Entity\Playlist;
use App\Playlist\Entity\PlaylistTrack;
use App\Playlist\Form\PlaylistType;
use App\Playlist\Repository\PlaylistRepository;
use App\Playlist\Repository\PlaylistTrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PlaylistPickerController extends AbstractController
{
    public function __construct(
        private readonly PlaylistRepository $playlistRepository,
        private readonly PlaylistTrackRepository $playlistTrackRepository,
    ) {}

    #[Route('/tracks/{id}/playlists', name: 'app_track_playlists', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    public function index(Track $track, #[CurrentUser] User $user): Response
    {
        return $this->renderPicker($track, $this->createPlaylistForm($track, $user));
    }

    #[Route('/tracks/{id}/playlists', name: 'app_track_playlists_create', requirements: ['id' => '[0-9]+'], methods: ['POST'])]
    public function create(Track $track, Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): Response
    {
        $form = $this->createPlaylistForm($track, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $playlist = $form->getData();

            $playlistTrack = new PlaylistTrack($user);
            $playlistTrack->setPosition(1);
            $playlistTrack->setPlaylist($playlist);
            $playlistTrack->setTrack($track);

            $em->persist($playlist);
            $em->persist($playlistTrack);
            $em->flush();

            return $this->redirectToRoute('app_track_playlists', ['id' => $track->getId()]);
        }

        return $this->renderPicker($track, $form, new Response(status: 422));
    }

    private function createPlaylistForm(Track $track, User $user): FormInterface
    {
        $playlist = new Playlist($user);

        return $this->createForm(PlaylistType::class, $playlist, ['action' => $this->generateUrl('app_track_playlists_create', ['id' => $track->getId()])]);
    }

    private function renderPicker(Track $track, FormInterface $form, ?Response $response = null): Response
    {
        return $this->render('playlist/_picker.html.twig', [
            'track' => $track,
            'playlists' => $this->playlistRepository->findBy([], ['name' => 'ASC']),
            'checkedIds' => $this->playlistTrackRepository->findPlaylistIds($track),
            'form' => $form,
        ], $response);
    }
}
