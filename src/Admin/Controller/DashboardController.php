<?php

namespace App\Admin\Controller;

use App\Account\Repository\UserRepository;
use App\Admin\Repository\ErrorReportRepository;
use App\Admin\Repository\SearchTimingRepository;
use App\Admin\Stats\DiskUsage;
use App\Shared\Stats\LocalCalendar;
use App\History\Repository\ListenRepository;
use App\Import\Enum\ImportStatus;
use App\Import\Repository\ImportJobRepository;
use App\Library\Repository\AlbumRepository;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(SearchTimingRepository $searchTimingRepository, ErrorReportRepository $errorReportRepository, ImportJobRepository $importJobRepository, UserRepository $userRepository, AlbumRepository $albumRepository, ArtistRepository $artistRepository, TrackRepository $trackRepository, ListenRepository $listenRepository, DiskUsage $diskUsage): Response
    {
        $since = new \DateTimeImmutable('-30 days');

        // The charts: in French local time, every day / hour / month present.
        $calendar = new LocalCalendar();
        $listensByDay = $calendar->lastDays($listenRepository->countPerHour($calendar->daysAgo(30)), 30);

        return $this->render('admin/index.html.twig', [
            'nbTracks' => $trackRepository->count(),
            'nbArtists' => $artistRepository->count(),
            'nbAlbums' => $albumRepository->count(),
            'nbComptes' => $userRepository->count(),

            'nbImportPending' => $importJobRepository->count(['status' => ImportStatus::Pending]),
            'nbImportDone' => $importJobRepository->count(['status' => ImportStatus::Done]),
            'nbImportDuplicate' => $importJobRepository->count(['status' => ImportStatus::Duplicate]),
            'nbImportFailed' => $importJobRepository->count(['status' => ImportStatus::Failed]),

            'totalDuration' => $trackRepository->totalDuration(),
            'nbCleaned' => $trackRepository->countCleaned(),

            'nbUnresolvedErrors' => $errorReportRepository->count(['resolvedAt' => null]),
            'importDurations' => $importJobRepository->durationStats(),

            'averageDuration' => $searchTimingRepository->averageDuration($since),
            'nbSuccessedSearch' => $searchTimingRepository->countSince($since, false),
            'nbFailedSearch' => $searchTimingRepository->countSince($since, true),

            'nbListens' => $listenRepository->count(),
            'nbListensMonth' => array_sum(array_column($listensByDay, 'value')),
            'listenedSecondsMonth' => $listenRepository->listenedSeconds($calendar->daysAgo(30)),
            'listensByDay' => $listensByDay,
            'listensByHour' => $calendar->hoursOfDay($listenRepository->countPerHour()),
            'topTracks' => $listenRepository->topTracks(8),
            'topArtists' => $listenRepository->topArtists(8),
            'listensPerAccount' => $listenRepository->perAccount(),
            'tracksByMonth' => $calendar->lastMonths($trackRepository->countAddedPerHour($calendar->monthsAgo(12)), 12),
            'disk' => $diskUsage->measure(),
        ]);
    }
}
