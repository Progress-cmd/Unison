<?php

namespace App\Playback\Controller;

use App\Library\Entity\Track;
use App\Library\Repository\TrackRepository;
use App\Playback\Service\QueueItems;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MixController extends AbstractController
{
    #[Route('/tracks/{id}/mix', name: 'app_track_mix', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Track $track, TrackRepository $trackRepository, QueueItems $queueItems): Response
    {
        return $this->json($this->queueFrom($track, $trackRepository, $queueItems));
    }

    #[Route('/tracks/random/mix', name: 'app_random_mix', methods: ['GET'])]
    public function random(TrackRepository $trackRepository, QueueItems $queueItems): Response
    {
        $tracks = $trackRepository->findRandom(1);

        if (!$tracks) {
            return new Response(null, 204);
        }

        return $this->json($this->queueFrom($tracks[0], $trackRepository, $queueItems));
    }

    private function queueFrom(Track $track, TrackRepository $trackRepository, QueueItems $queueItems): array
    {
        $tracks = array_merge([$track], $trackRepository->findRandom(49, $track));

        return $queueItems->fromTracks($tracks);
    }
}
