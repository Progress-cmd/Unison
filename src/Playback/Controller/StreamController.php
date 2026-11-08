<?php

namespace App\Playback\Controller;

use App\Library\Entity\Track;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StreamController extends AbstractController
{
    #[Route('/tracks/{id}/stream', name: 'app_track_stream', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Track $track, #[Autowire('%env(resolve:MUSIC_DIR)%')] string $musicDir): BinaryFileResponse
    {
        $path = $musicDir . '/' . $track->getFileName();

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'audio/mp4'], public: false);
    }
}
