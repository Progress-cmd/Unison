<?php

namespace App\Playback\Service;

use Doctrine\ORM\Tools\Pagination\Window;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

// A long list is only partly on the page (50 rows, more while scrolling). When a track of it
// is played, track_list_controller.js asks the same page with ?queue=<row>, and the player gets
// the whole list as its queue, not only the rows already shown.
final class ListQueue
{
    // Kept before the track clicked, for "previous".
    private const BEFORE = 50;
    // As many as the saved playback state keeps (PlaybackStateController).
    private const SIZE = 500;

    public function __construct(
        private readonly QueueItems $queueItems,
    ) {
    }

    /**
     * The queue around the row asked for, or null when the request isn't a queue request.
     *
     * @param callable(Window): list<\App\Library\Entity\Track> $tracks the tracks of the list in a window
     */
    public function response(Request $request, callable $tracks): ?JsonResponse
    {
        if (!$request->query->has('queue')) {
            return null;
        }

        $index = max(0, $request->query->getInt('queue'));
        $first = max(0, $index - self::BEFORE);

        return new JsonResponse([
            'tracks' => $this->queueItems->fromTracks($tracks(new Window($first, self::SIZE))),
            // Where the track clicked is in this window.
            'index' => $index - $first,
        ]);
    }
}
