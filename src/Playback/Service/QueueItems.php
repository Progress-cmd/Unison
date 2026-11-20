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

    // Same fields as the data-track JSON of templates/library/_track.html.twig.
    private function fromTrack(Track $track): array
    {
        $album = $track->getAlbum();

        return [
            'id' => $track->getId(),
            'url' => $this->urlGenerator->generate('app_track_stream', ['id' => $track->getId()]),
            'title' => $track->getTitle(),
            'artists' => implode(', ', array_map(fn ($artist) => $artist->getName(), $track->getArtists()->toArray())),
            'cover' => $track->getCover(),
            'waveform' => $track->getWaveform(),
            'playlistsUrl' => $this->urlGenerator->generate('app_track_playlists', ['id' => $track->getId()]),
            // For the "Voir l'album" / "Voir <artiste>" links of the options sheet.
            'album' => $album ? [
                'title' => $album->getTitle(),
                'url' => $this->urlGenerator->generate('app_albums_show', ['id' => $album->getId()]),
            ] : null,
            'artistLinks' => array_map(fn ($artist) => [
                'name' => $artist->getName(),
                'url' => $this->urlGenerator->generate('app_artists_show', ['id' => $artist->getId()]),
            ], $track->getArtists()->toArray()),
        ];
    }
}
