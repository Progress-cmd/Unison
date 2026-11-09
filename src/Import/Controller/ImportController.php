<?php

namespace App\Import\Controller;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Import\Form\ImportType;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use App\Import\Repository\ImportJobRepository;
use App\Import\Service\YouTubeOEmbed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;

final class ImportController extends AbstractController
{
    #[Route('/import', name: 'app_import')]
    public function index(Request $request, MessageBusInterface $messageBus, #[CurrentUser] User $user, EntityManagerInterface $em, YouTubeOEmbed $youTubeOEmbed): Response
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

        return $this->render('import/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/import/jobs', name: 'app_import_jobs')]
    public function jobs(ImportJobRepository $importJobRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('import/_jobs.html.twig', [
            'jobs' => $importJobRepository->findRecentFor($user),
        ]);
    }

    private function isPlaylist(string $url): bool
    {
        return str_contains($url, '/playlist') || (str_contains($url, 'list=') && !str_contains($url, 'v='));
    }
}
