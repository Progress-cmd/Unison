<?php

namespace App\Playback\Controller;

use App\Library\Entity\Track;
use App\Library\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MixController extends AbstractController
{
    #[Route('/tracks/{id}/mix', name: 'app_track_mix', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Track $track, TrackRepository $trackRepository): Response
    {
        $tracks = array_merge([$track], $trackRepository->findRandom(49, $track));

        $tracks = array_map($this->toQueueItem(...), $tracks);

        return $this->json($tracks);
    }

    private function toQueueItem(Track $track): array
    {
        return [
            'url' => $this->generateUrl('app_track_stream', ['id' => $track->getId()]),
            'title' => $track->getTitle(),
            'artists' => implode(', ', array_map(fn($a) => $a->getName(), $track->getArtists()->toArray())),
            'cover' => $track->getCover(),
            'waveform' => $track->getWaveform(),
        ];
    }
}
