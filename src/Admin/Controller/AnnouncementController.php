<?php

namespace App\Admin\Controller;

use App\Account\Entity\User;
use App\Admin\Form\AnnouncementCreateType;
use App\Announcement\Entity\Announcement;
use App\Announcement\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// Publishing the announcements every account sees once (templates/shared/_announcement.html.twig).
final class AnnouncementController extends AbstractController
{
    #[Route('/admin/announcements', name: 'app_admin_announcements')]
    public function index(Request $request, #[CurrentUser] User $user, AnnouncementRepository $announcementRepository, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AnnouncementCreateType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $announcement = new Announcement(
                trim($form->get('title')->getData()),
                trim($form->get('content')->getData()),
                $form->get('kind')->getData(),
            );

            $em->persist($announcement);
            $em->flush();

            // Whoever wrote it has read it.
            $announcementRepository->markSeen($announcement, $user);

            $this->addFlash('success', 'Annonce publiée. Chaque compte la verra une fois, à sa prochaine page.');

            return $this->redirectToRoute('app_admin_announcements');
        }

        return $this->render('admin/announcements.html.twig', [
            'form' => $form,
            'rows' => $announcementRepository->findAllWithCounts(),
        ]);
    }

    // Withdraws it (no longer shown to those who haven't seen it), or puts it back. Who has
    // seen it is kept: putting it back doesn't show it to them again.
    #[Route('/admin/announcements/{id}/toggle', name: 'app_admin_announcements_toggle', methods: ['POST'])]
    public function toggle(Request $request, Announcement $announcement, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('announcement_toggle', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $announcement->setActive(!$announcement->isActive());
        $em->flush();

        $this->addFlash('success', $announcement->isActive()
            ? 'Annonce remise : ceux qui ne l\'ont pas encore vue la verront.'
            : 'Annonce retirée : elle ne s\'affiche plus.');

        return $this->redirectToRoute('app_admin_announcements');
    }

    // Who saw it goes with it (cascade): published again, it would show to everyone.
    #[Route('/admin/announcements/{id}/delete', name: 'app_admin_announcements_delete', methods: ['POST'])]
    public function delete(Request $request, Announcement $announcement, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('announcement_delete', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $em->remove($announcement);
        $em->flush();

        $this->addFlash('success', 'Annonce supprimée.');

        return $this->redirectToRoute('app_admin_announcements');
    }
}
