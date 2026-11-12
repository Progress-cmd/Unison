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

        return $this->redirectToRoute('app_playlist_show',
            ['id' => $playlist->getId()]
        );
    }
}
