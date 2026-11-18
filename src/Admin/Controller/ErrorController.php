<?php

namespace App\Admin\Controller;

use App\Admin\Entity\ErrorReport;
use App\Admin\Repository\ErrorReportRepository;
use App\Import\Enum\ImportStatus;
use App\Import\Service\ImportLauncher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ErrorController extends AbstractController
{
    #[Route('/admin/errors', name: 'app_admin_errors')]
    public function index(ErrorReportRepository $errorReportRepository): Response
    {
        return $this->render('admin/errors.html.twig', [
            'errors' => $errorReportRepository->findUnresolved(),
        ]);
    }

    #[Route('/admin/errors/{id}/resolve', name: 'app_admin_errors_resolve', methods: ['POST'])]
    public function resolve(Request $request, EntityManagerInterface $em, ErrorReport $error): Response
    {
        if (!$this->isCsrfTokenValid('error_resolve', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $error->setResolvedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('app_admin_errors');
    }

    #[Route('/admin/errors/{id}/retry', name: 'app_admin_errors_retry', methods: ['POST'])]
    public function retry(Request $request, EntityManagerInterface $em, ImportLauncher $importLauncher, ErrorReport $error): Response
    {
        if (!$this->isCsrfTokenValid('error_retry', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $job = $error->getImportJob();

        if (null === $job) {
            throw new BadRequestHttpException('Cette erreur ne vient pas d\'un import.');
        }

        $job->setStatus(ImportStatus::Pending);
        $job->setError(null);
        $error->setResolvedAt(new \DateTimeImmutable());

        $em->flush();

        $importLauncher->launch($job);

        return $this->redirectToRoute('app_admin_errors');
    }
}
