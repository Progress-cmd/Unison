<?php

namespace App\Library\Twig;

use App\Library\Repository\AlbumRepository;
use App\Library\Repository\ArtistRepository;
use App\Library\Repository\TrackRepository;
use Twig\Attribute\AsTwigFunction;

final class LibraryStatsExtension
{
    public function __construct(
        private readonly TrackRepository $tracks,
        private readonly ArtistRepository $artists,
        private readonly AlbumRepository $albums,
    ) {}

    #[AsTwigFunction('library_stats')]
    public function libraryStats(): array
    {
        return [
            'tracks' => $this->tracks->count(),
            'artists' => $this->artists->count(),
            'albums' => $this->albums->count(),
        ];
    }
}