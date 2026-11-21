<?php

namespace App\Admin\Controller;

use App\Admin\Maintenance\CookiesFile;
use App\Admin\Maintenance\MediaCheck;
use App\Import\Message\RepairTrack;
use App\Import\Service\YtDlpClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

// The health of the files (audio, covers), yt-dlp's version and the YouTube cookies.
final class MaintenanceController extends AbstractController
{
    // Past this age, yt-dlp may no longer keep up with YouTube's changes.
    private const YTDLP_OLD_DAYS = 45;

    #[Route('/admin/maintenance', name: 'app_admin_maintenance')]
    public function index(MediaCheck $mediaCheck, CookiesFile $cookiesFile, YtDlpClient $ytDlpClient): Response
    {
        $report = $mediaCheck->report();
        $version = $ytDlpClient->version();
        $released = $version ? \DateTimeImmutable::createFromFormat('!Y.m.d', substr($version, 0, 10)) : false;
        $age = $released ? $released->diff(new \DateTimeImmutable('today'))->days : null;

        return $this->render('admin/maintenance.html.twig', [
            'report' => $report,
            'nbToRepair' => count($mediaCheck->tracksToRepair($report)),
            'cookies' => $cookiesFile->status(),
            'ytDlpVersion' => $version,
            'ytDlpAge' => $age,
            'ytDlpOld' => null !== $age && $age > self::YTDLP_OLD_DAYS,
        ]);
    }

    // Albums first, at once (the cover of one of their tracks, already here), then each damaged
    // track to the worker, which downloads what's missing.
    #[Route('/admin/maintenance/repair', name: 'app_admin_maintenance_repair', methods: ['POST'])]
    public function repair(Request $request, MediaCheck $mediaCheck, MessageBusInterface $bus, EntityManagerInterface $em): Response
    {
        $this->checkToken($request, 'maintenance_repair');

        $report = $mediaCheck->report();
        $broken = array_flip(array_map(fn ($track) => $track->getId(), $report['brokenCovers']));

        foreach ($report['brokenAlbumCovers'] as $album) {
            foreach ($album->getTracks() as $track) {
                if (!isset($broken[$track->getId()]) && null !== $track->getCover()) {
                    $album->setCover($track->getCover());
                    break;
                }
            }
        }

        $em->flush();

        $tracks = $mediaCheck->tracksToRepair($report);

        foreach ($tracks as $track) {
            $bus->dispatch(new RepairTrack($track->getId(), $track->getSourceUrl()));
        }

        $this->addFlash('success', $tracks
            ? sprintf('%d titre%s en réparation : le worker retélécharge ce qui manque. Les échecs arrivent sur la page Erreurs.', count($tracks), count($tracks) > 1 ? 's' : '')
            : 'Rien à réparer.');

        return $this->redirectToRoute('app_admin_maintenance');
    }

    #[Route('/admin/maintenance/orphans', name: 'app_admin_maintenance_orphans', methods: ['POST'])]
    public function deleteOrphans(Request $request, MediaCheck $mediaCheck): Response
    {
        $this->checkToken($request, 'maintenance_orphans');

        $deleted = $mediaCheck->deleteOrphans();
        $size = $deleted['bytes'] >= 1048576
            ? number_format($deleted['bytes'] / 1048576, 1, ',', ' ').' Mo'
            : number_format($deleted['bytes'] / 1024, 0, ',', ' ').' Ko';

        $this->addFlash('success', sprintf('%d fichier%s supprimé%s, %s libérés.', $deleted['files'], $deleted['files'] > 1 ? 's' : '', $deleted['files'] > 1 ? 's' : '', $size));

        return $this->redirectToRoute('app_admin_maintenance');
    }

    #[Route('/admin/maintenance/cookies', name: 'app_admin_maintenance_cookies', methods: ['POST'])]
    public function uploadCookies(Request $request, CookiesFile $cookiesFile): Response
    {
        $this->checkToken($request, 'maintenance_cookies');

        $file = $request->files->get('cookies');

        if (null === $file || !$file->isValid()) {
            $this->addFlash('error', 'Choisis le fichier cookies.txt à envoyer.');
        } elseif ($file->getSize() > 1048576) {
            $this->addFlash('error', 'Fichier trop gros pour un fichier de cookies (1 Mo au plus).');
        } else {
            try {
                $count = $cookiesFile->replace($file);
                $this->addFlash('success', sprintf('Cookies remplacés (%d). yt-dlp s\'en sert dès le prochain import.', $count));
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_maintenance');
    }

    #[Route('/admin/maintenance/cookies/delete', name: 'app_admin_maintenance_cookies_delete', methods: ['POST'])]
    public function deleteCookies(Request $request, CookiesFile $cookiesFile): Response
    {
        $this->checkToken($request, 'maintenance_cookies_delete');

        $cookiesFile->remove();
        $this->addFlash('success', 'Cookies retirés : yt-dlp travaille sans compte.');

        return $this->redirectToRoute('app_admin_maintenance');
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
