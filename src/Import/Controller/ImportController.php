<?php

namespace App\Import\Controller;

use App\Account\Entity\User;
use App\Admin\Entity\ErrorReport;
use App\Admin\Enum\ErrorSource;
use App\Import\Entity\ImportJob;
use App\Import\Form\ImportType;
use App\Import\Repository\ImportJobRepository;
use App\Import\Service\ImportLauncher;
use App\Import\Service\TitleCleaner;
use App\Import\Service\YouTubeMusicSearch;
use App\Import\Service\YouTubeOEmbed;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

final class ImportController extends AbstractController
{
    private const WATCH_URL = 'https://www.youtube.com/watch?v=';
    private const PLAYLIST_URL = 'https://www.youtube.com/playlist?list=';

    #[Route('/import', name: 'app_import')]
    public function index(Request $request, ImportLauncher $importLauncher, #[CurrentUser] User $user, EntityManagerInterface $em, YouTubeOEmbed $youTubeOEmbed, TrackRepository $trackRepository, FavoriteTrackRepository $favoriteTrackRepository): Response
    {
        $form = $this->createForm(ImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();

            $jobs = [];

            foreach ($urls as $url) {
                $job = new ImportJob($url, $user);

                $job->setTitle($youTubeOEmbed->fetchTitle($url));

                $em->persist($job);

                $jobs[] = $job;

            }
            $em->flush();

            foreach ($jobs as $job) {
                $importLauncher->launch($job);
            }

            $this->addFlash('success', 'Imports ajoutés à la file d\'attente.');

            return $this->redirectToRoute('app_import');
        }

        $session = $request->getSession();

        // "q" in the URL: a search was just sent (an empty one clears it). Keep it for later.
        // No "q": the page was opened from the menu. Take back the last search.
        if ($request->query->has('q')) {
            $query = trim($request->query->getString('q'));
            $session->set('import_query', $query);
        } else {
            $query = $session->get('import_query', '');
        }

        $results = [];

        if ('' !== $query) {
            $results = $trackRepository->search($query);
        }

        return $this->render('import/index.html.twig', [
            'form' => $form,
            'q' => $query,
            'results' => $results,
            'favoriteIds' => $favoriteTrackRepository->findTrackIds($user),
        ]);
    }

    #[Route('/import/jobs', name: 'app_import_jobs')]
    public function jobs(ImportJobRepository $importJobRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('import/_jobs.html.twig', [
            'jobs' => $importJobRepository->findRecentFor($user),
        ]);
    }

    #[Route('/import/youtube', name: 'app_import_youtube')]
    public function youtube(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user, ImportJobRepository $importJobRepository, TitleCleaner $titleCleaner, YouTubeMusicSearch $youTubeMusicSearch, TrackRepository $trackRepository, AlbumRepository $albumRepository): Response
    {
        $query = trim($request->query->getString('q'));

        try {
            $results = $youTubeMusicSearch->search($query);
            $albums = $youTubeMusicSearch->searchAlbums($query);
        } catch (\RuntimeException $e) {
            $error = new ErrorReport(ErrorSource::YouTubeMusic, $e->getPrevious()?->getMessage() ?? $e->getMessage(), mb_substr($query, 0, 255), null);

            $em->persist($error);
            $em->flush();

            return $this->render('import/_youtube.html.twig', [
                'error' => true,
            ]);
        }

        $existingUrlsTrack = $trackRepository->findExistingSourceUrls(array_column($results, 'url'));
        $existingUrlsAlbum = $albumRepository->findExistingSourceUrls(array_column($albums, 'url'));

        $results = array_filter($results, fn (array $result) => !in_array($result['url'], $existingUrlsTrack, true));
        $albums = array_filter($albums, fn (array $album) => !in_array($album['url'], $existingUrlsAlbum, true));

        $queuedUrls = $importJobRepository->findPendingUrls($user, array_merge(array_column($results, 'url'), array_column($albums, 'url')));

        foreach ($results as $key => $result) {
            $results[$key]['title'] = $titleCleaner->clean($result['title'])->title;
        }

        return $this->render('import/_youtube.html.twig', [
            'results' => $results,
            'albums' => $albums,
            'queuedUrls' => $queuedUrls,
        ]);
    }

    #[Route('/import/youtube/add', name: 'app_import_youtube_add', methods: ['POST'])]
    public function add(Request $request, ImportLauncher $importLauncher, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('import_youtube', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $url = $request->request->getString('url');
        $title = $request->request->getString('title');

        if (str_starts_with($url, self::WATCH_URL)) {
            $prefix = self::WATCH_URL;
        } elseif (str_starts_with($url, self::PLAYLIST_URL)) {
            $prefix = self::PLAYLIST_URL;
        } else {
            throw new BadRequestHttpException('Lien YouTube invalide.');
        }

        $job = new ImportJob($url, $user);

        $job->setTitle($title);

        $em->persist($job);
        $em->flush();

        $importLauncher->launch($job);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('import/youtube_add.stream.html.twig', [
            'id' => substr($url, strlen($prefix)),
        ]);
    }
}
