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
        $tracks = array_merge([$track], $trackRepository->findRandom(49, $track));

        $tracks = $queueItems->fromTracks($tracks);

        return $this->json($tracks);
    }
}
