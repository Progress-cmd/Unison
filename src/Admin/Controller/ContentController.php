<?php

namespace App\Admin\Controller;

use App\Admin\Form\ArtistMergeType;
use App\Admin\Form\TrackEditType;
use App\Library\Entity\Album;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use App\Library\Service\ArtistMerger;
use App\Library\Service\ContentRemover;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends AbstractController
{
    #[Route('/admin/content', name: 'app_admin_content')]
    public function index(Request $request, TrackRepository $trackRepository, AlbumRepository $albumRepository, ArtistRepository $artistRepository): Response
    {
        $query = trim($request->query->getString('q'));

        if ('' !== $query) {
            $albums = $albumRepository->search($query, 10);
            $tracks = $trackRepository->search($query, 50);
            $artists = $artistRepository->search($query, 10);
        } else {
            $albums = $albumRepository->findAllAlbum(6);
            $tracks = $trackRepository->findBy([], ['createdAt' => 'DESC'], 50);
            $artists = [];
        }

        return $this->render('admin/content.html.twig', [
            'q' => $query,
            'albums' => $albums,
            'tracks' => $tracks,
            'artists' => $artists,
        ]);
    }

    #[Route('/admin/content/albums/{id}', name: 'app_admin_content_album')]
    public function album(Album $album): Response
    {
        return $this->render('admin/content_album.html.twig', [
            'album' => $album,
        ]);
    }

    #[Route('/admin/content/tracks/{id}/edit', name: 'app_admin_content_edit')]
    public function edit(Request $request, EntityManagerInterface $em, Track $track): Response
    {
        $form = $this->createForm(TrackEditType::class, $track);

        $title = $track->getTitle();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Titre modifié.');

            return $this->redirectToRoute('app_admin_content');
        }

        return $this->render('admin/content_edit.html.twig', [
            'form' => $form,
            'title' => $title,
            'track' => $track,
        ]);
    }

    #[Route('/admin/content/tracks/{id}/delete', name: 'app_admin_content_delete_track', methods: ['POST'])]
    public function deleteTrack(Request $request, ContentRemover $contentRemover, Track $track): Response
    {
        if (!$this->isCsrfTokenValid('track_delete', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $contentRemover->removeTrack($track);

        $this->addFlash('success', 'Titre supprimé.');

        return $this->redirectToRoute('app_admin_content');
    }

    #[Route('/admin/content/albums/{id}/delete', name: 'app_admin_content_delete_album', methods: ['POST'])]
    public function deleteAlbum(Request $request, ContentRemover $contentRemover, Album $album): Response
    {
        if (!$this->isCsrfTokenValid('album_delete', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $nbTracks = count($album->getTracks());

        $contentRemover->removeAlbum($album);

        $this->addFlash('success', 'Album supprimé et ses ' . $nbTracks . ' titres.');

        return $this->redirectToRoute('app_admin_content');
    }

    #[Route('/admin/content/artists/{id}', name: 'app_admin_content_artist')]
    public function artist(Request $request, Artist $artist, ArtistMerger $artistMerger): Response
    {
        $form = $this->createForm(ArtistMergeType::class, null, ['exclude' => $artist]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $target = $form->get('target')->getData();
            $name = $artist->getName();

            $artistMerger->merge($artist, $target);

            $this->addFlash('success', sprintf('« %s » a été fusionné dans « %s ».', $name, $target->getName()));

            return $this->redirectToRoute('app_admin_content_artist', ['id' => $target->getId()]);
        }

        return $this->render('admin/content_artist.html.twig', [
            'artist' => $artist,
            'form' => $form,
        ]);
    }
}
