<?php

namespace App\Import\Controller;

use App\Account\Entity\User;
use App\Import\Form\ImportType;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ImportController extends AbstractController
{
    #[Route('/import', name: 'app_import')]
    public function index(Request $request, MessageBusInterface $messageBus, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(ImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url = $form->get('url')->getData();

            if ($this->isPlaylist($url)) {
                $messageBus->dispatch(new ImportPlaylist($url, $user->getId()));
            } else {
                $messageBus->dispatch(new ImportTrack($url, $user->getId()));
            }

            $this->addFlash('success', 'Import ajouté à la file d\'attente.');

            return $this->redirectToRoute('app_import');
        }

        return $this->render('import/index.html.twig', [
            'form' => $form,
        ]);
    }

    private function isPlaylist(string $url): bool
    {
        return str_contains($url, '/playlist') || (str_contains($url, 'list=') && !str_contains($url, 'v='));
    }
}
