<?php

namespace App\Import\Controller;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Import\Form\ImportType;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use App\Import\Repository\ImportJobRepository;
use App\Import\Service\TitleCleaner;
use App\Import\Service\YouTubeMusicSearch;
use App\Import\Service\YouTubeOEmbed;
use App\Library\Repository\FavoriteTrackRepository;
use App\Library\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

final class ImportController extends AbstractController
{
    private const WATCH_URL = 'https://www.youtube.com/watch?v=';

    #[Route('/import', name: 'app_import')]
    public function index(Request $request, MessageBusInterface $messageBus, #[CurrentUser] User $user, EntityManagerInterface $em, YouTubeOEmbed $youTubeOEmbed, TrackRepository $trackRepository, FavoriteTrackRepository $favoriteTrackRepository): Response
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
                if ($this->isPlaylist($job->getUrl())) {
                    $messageBus->dispatch(new ImportPlaylist($job->getUrl(), $user->getId(), $job->getId()));
                } else {
                    $messageBus->dispatch(new ImportTrack($job->getUrl(), $user->getId(), jobId: $job->getId()));
                }
            }

            $this->addFlash('success', 'Imports ajoutés à la file d\'attente.');

            return $this->redirectToRoute('app_import');
        }

        $query = $request->query->getString('q');
        $results = [];

        $query = trim($query);

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
    public function youtube(Request $request, TitleCleaner $titleCleaner, YouTubeMusicSearch $youTubeMusicSearch, TrackRepository $trackRepository): Response
    {
        $query = trim($request->query->getString('q'));

        try {
            $results = $youTubeMusicSearch->search($query);
        } catch (\RuntimeException) {
            return $this->render('import/_youtube.html.twig', [
                'error' => true,
            ]);
        }

        $existingUrls = $trackRepository->findExistingSourceUrls(array_column($results, 'url'));

        $results = array_filter($results, fn (array $result) => !in_array($result['url'], $existingUrls, true));

        foreach ($results as $key => $result) {
            $results[$key]['title'] = $titleCleaner->clean($result['title'])->title;
        }

        return $this->render('import/_youtube.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/import/youtube/add', name: 'app_import_youtube_add', methods: ['POST'])]
    public function add(Request $request, MessageBusInterface $messageBus, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('import_youtube', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $url = $request->request->getString('url');
        $title = $request->request->getString('title');

        if (!str_starts_with($url, self::WATCH_URL)) {
            throw new BadRequestHttpException('Lien YouTube invalide.');
        }

        $job = new ImportJob($url, $user);

        $job->setTitle($title);

        $em->persist($job);
        $em->flush();

        $messageBus->dispatch(new ImportTrack($job->getUrl(), $user->getId(), jobId: $job->getId()));

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('import/youtube_add.stream.html.twig', [
            'id' => substr($url, strlen(self::WATCH_URL)),
        ]);
    }

    private function isPlaylist(string $url): bool
    {
        return str_contains($url, '/playlist') || (str_contains($url, 'list=') && !str_contains($url, 'v='));
    }
}
