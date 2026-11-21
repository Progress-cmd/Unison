<?php

namespace App\History\Controller;

use App\Account\Entity\User;
use App\History\Entity\Listen;
use App\History\Repository\ListenRepository;
use App\Library\Repository\FavoriteTrackRepository;
use App\Playback\Service\ListQueue;
use App\Shared\Pagination\ListPage;
use App\Shared\Stats\LocalCalendar;
use Doctrine\ORM\Tools\Pagination\Window;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// « Mes écoutes »: the account's listening figures, then its whole history.
final class HistoryController extends AbstractController
{
    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, ListenRepository $listenRepository, FavoriteTrackRepository $favoriteTrackRepository, ListQueue $listQueue): Response
    {
        // Playing a track: the whole history as a queue (JSON for the player), one track per listen.
        $queue = $listQueue->response($request, fn (Window $window) => array_map(
            fn (Listen $listen) => $listen->getTrack(),
            $listenRepository->paginateRecent($user, $window)->getItems(),
        ));

        if ($queue) {
            return $queue;
        }

        $page = $listenRepository->paginateRecent($user, ListPage::window($request));
        $favoriteIds = $favoriteTrackRepository->findTrackIds($user);

        // The next 50, while scrolling: the rows alone.
        if (ListPage::isNextRows($request)) {
            return $this->render('history/_rows.html.twig', [
                'page' => $page,
                'favoriteIds' => $favoriteIds,
                // Day of the last row already shown: its heading isn't repeated.
                'previousDay' => $request->query->getString('day'),
            ]);
        }

        // In French local time, every day and moment present (see LocalCalendar).
        $calendar = new LocalCalendar();
        $week = $calendar->lastDays($listenRepository->countPerHour($calendar->daysAgo(7), $user), 7, true);

        return $this->render('history/index.html.twig', [
            'page' => $page,
            'favoriteIds' => $favoriteIds,
            'previousDay' => '',

            'nbListens' => $listenRepository->count(['user' => $user]),
            'listenedSeconds' => $listenRepository->listenedSeconds(null, $user),
            'nbDistinctTracks' => $listenRepository->countDistinctTracks($user),
            'nbListensWeek' => array_sum(array_column($week, 'value')),
            'week' => $week,
            'moments' => $calendar->moments($listenRepository->countPerHour(null, $user)),
            'topTracks' => $listenRepository->topTracks(5, $user),
            'topArtists' => $listenRepository->topArtists(5, $user),
        ]);
    }
}
