<?php

namespace App\Playback\Service;

use App\Library\Entity\Track;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QueueItems
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function fromTracks(array $tracks): array
    {
        return array_map($this->fromTrack(...), $tracks);
    }

    private function fromTrack(Track $track): array
    {
        return [
            'id' => $track->getId(),
            'url' => $this->urlGenerator->generate('app_track_stream', ['id' => $track->getId()]),
            'title' => $track->getTitle(),
            'artists' => implode(', ', array_map(fn ($artist) => $artist->getName(), $track->getArtists()->toArray())),
            'cover' => $track->getCover(),
            'waveform' => $track->getWaveform(),
            'playlistsUrl' => $this->urlGenerator->generate('app_track_playlists', ['id' => $track->getId()]),
        ];
    }
}
