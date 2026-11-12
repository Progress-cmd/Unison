<?php

namespace App\Playlist\Controller;

use App\Account\Entity\User;
use App\Library\Repository\TrackRepository;
use App\Playlist\Entity\Playlist;
use App\Playlist\Entity\PlaylistTrack;
use App\Playlist\Repository\PlaylistTrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class PlaylistTrackController extends AbstractController
{
    #[Route('/playlists/{id}/tracks', name: 'app_playlist_add_track', methods: ['POST'])]
    public function index(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user, Playlist $playlist, TrackRepository $trackRepository, PlaylistTrackRepository $playlistTrackRepository): Response
    {
        if (!$this->isCsrfTokenValid('playlist_add_track', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $trackId = $request->request->getInt('track');
        $track = $trackRepository->find($trackId);

        if (null === $track) {
            throw $this->createNotFoundException();
        }

        $alreadyIn = $playlistTrackRepository->findOneBy(['track' => $track, 'playlist' => $playlist]);

        if (!is_null($alreadyIn)) {            
            $this->addFlash('warning', 'Titre déjà dans '.$playlist->getName());
            return $this->redirectToRoute('app_playlist_show',
                ['id' => $playlist->getId()]
            );
        }

        $playlistTrack = new PlaylistTrack($user);
        
        $playlistTrack->setPlaylist($playlist);
        $playlistTrack->setTrack($track);
        $playlistTrack->setPosition($playlistTrackRepository->findPositionMax($playlist) + 1);
        $playlist->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($playlistTrack);
        $em->flush();

        $this->addFlash('success', 'Titre ajouté à '.$playlist->getName());

        return $this->redirectToRoute('app_library',
            ['id' => $playlist->getId()]
        );
    }

    #[Route('/playlists/{id}/tracks/remove', name: 'app_playlist_remove_track', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, PlaylistTrackRepository $playlistTrackRepository, Playlist $playlist): Response
    {
        if (!$this->isCsrfTokenValid('playlist_remove_track', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $track = $request->request->getInt('track');
        
        $playlistTrack = $playlistTrackRepository->findOneBy(['track' => $track, 'playlist' => $playlist]);

        if (null === $playlistTrack) {
            $this->addFlash('warning', 'Ce titre n\'est plus dans la playlist');
            return $this->redirectToRoute('app_playlist_show',
                ['id' => $playlist->getId()]
            );
        }

        $em->remove($playlistTrack);
        $em->flush();

        $this->addFlash('success', 'Titre retiré de '.$playlist->getName());

        return $this->redirectToRoute('app_playlist_show',
            ['id' => $playlist->getId()]
        );
    }

    #[Route('/playlists/{id}/tracks/move', name: 'app_playlist_move_track', methods: ['POST'])]
    public function move(Request $request, EntityManagerInterface $em, PlaylistTrackRepository $playlistTrackRepository, Playlist $playlist): Response
    {
        if (!$this->isCsrfTokenValid('playlist_move_track', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $direction = $request->request->getString('direction');

        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->redirectToRoute('app_playlist_show',
                ['id' => $playlist->getId()]
            );
        }

        $trackId = $request->request->getInt('track');
        $playlistTrack = $playlistTrackRepository->findOneBy(['track' => $trackId, 'playlist' => $playlist]);

        if (null === $playlistTrack) {
            $this->addFlash('warning', 'Ce titre n\'est plus dans la playlist');
            return $this->redirectToRoute('app_playlist_show',
                ['id' => $playlist->getId()]
            );
        }

        $index = $playlist->getPlaylistTracks()->indexOf($playlistTrack);
        $neighbor = $playlist->getPlaylistTracks()->get($direction === 'up' ? $index - 1 : $index + 1);

        if (null === $neighbor) {
            return $this->redirectToRoute('app_playlist_show',
                ['id' => $playlist->getId()]
            );
        }

        $position = $playlistTrack->getPosition();
        $playlistTrack->setPosition($neighbor->getPosition());
        $neighbor->setPosition($position);

        $playlist->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('app_playlist_show',
            ['id' => $playlist->getId()]
        );
    }
}
