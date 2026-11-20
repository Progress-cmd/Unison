<?php

namespace App\Admin\Controller;

use App\Account\Entity\User;
use App\Admin\Entity\ErrorReport;
use App\Admin\Enum\ErrorSource;
use App\Admin\Repository\ErrorReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// Receives the JavaScript errors of the logged-in visitors (assets/error_reporter.js) and
// files them on the Erreurs page. Not under /admin: every account sends, only admins read.
final class BrowserErrorController extends AbstractController
{
    #[Route('/errors/browser', name: 'app_browser_error', methods: ['POST'])]
    public function report(
        Request $request,
        #[CurrentUser] User $user,
        RateLimiterFactoryInterface $browserErrorLimiter,
        ErrorReportRepository $errorReportRepository,
        EntityManagerInterface $em,
    ): Response {
        // JSON only: a form posted from another site can't produce it.
        if ('json' !== $request->getContentTypeFormat()) {
            return new Response(null, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        if (!$browserErrorLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            return new Response(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = $request->toArray();
        $message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 500);

        if ('' === $message) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        // The same error, still not marked « Réglé »: one card is enough. The stored text starts
        // with the message (the stack trace follows it).
        $sameError = $errorReportRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.source = :source')
            ->andWhere('e.resolvedAt IS NULL')
            ->andWhere('e.message LIKE :start')
            ->setParameter('source', ErrorSource::Browser)
            ->setParameter('start', addcslashes($message, '%_\\').'%')
            ->getQuery()
            ->getSingleScalarResult();

        if ($sameError > 0) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $stack = mb_substr(trim((string) ($data['stack'] ?? '')), 0, 2000);

        // Where, who and with what: enough to reproduce it.
        $context = sprintf('%s — %s — %s', (string) ($data['page'] ?? '?'), $user->getUsername(), $request->headers->get('User-Agent', '?'));

        $em->persist(new ErrorReport(
            ErrorSource::Browser,
            '' !== $stack ? $message."\n\n".$stack : $message,
            mb_substr($context, 0, 255),
            null,
        ));
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
