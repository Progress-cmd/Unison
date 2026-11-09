<?php

namespace App\Import\Controller;

use App\Account\Entity\User;
use App\Import\Entity\ImportJob;
use App\Import\Form\ImportType;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use App\Import\Repository\ImportJobRepository;
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
    public function index(Request $request, MessageBusInterface $messageBus, #[CurrentUser] User $user, EntityManagerInterface $em, ImportJobRepository $importJobRepository): Response
    {
        $form = $this->createForm(ImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $urls = $form->get('urls')->getData();

            foreach ($urls as $url) {
                $em->persist(new ImportJob($url, $user));

                if ($this->isPlaylist($url)) {
                    $messageBus->dispatch(new ImportPlaylist($url, $user->getId()));
                } else {
                    $messageBus->dispatch(new ImportTrack($url, $user->getId()));
                }
            }
            $em->flush();

            $this->addFlash('success', 'Imports ajoutés à la file d\'attente.');

            return $this->redirectToRoute('app_import');
        }

        return $this->render('import/index.html.twig', [
            'form' => $form,
            'jobs' => $importJobRepository->findRecentFor($user),
        ]);
    }

    private function isPlaylist(string $url): bool
    {
        return str_contains($url, '/playlist') || (str_contains($url, 'list=') && !str_contains($url, 'v='));
    }
}
