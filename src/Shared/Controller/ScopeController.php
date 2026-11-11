<?php

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * App-wide "Nous / Moi" switch: shows everyone's content or only the user's.
 * The choice lives in the session, read back with get('scope', 'all').
 */
final class ScopeController extends AbstractController
{
    private const SCOPES = ['all', 'mine'];

    #[Route('/scope', name: 'app_scope', methods: ['POST'])]
    public function index(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('scope', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $scope = $request->request->getString('scope');

        if (in_array($scope, self::SCOPES, true)) {
            $request->getSession()->set('scope', $scope);
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
    }
}
