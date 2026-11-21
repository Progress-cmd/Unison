<?php

namespace App\Import\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

// Covers are kept on the server (public/covers), square 400 × 400, named after the YouTube video:
// a link to YouTube's images can stop working any day.
class CoverDownloader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/covers')]
        private readonly string $coversDir,
    ) {}

    /**
     * The cover of this video on the server: from $remoteUrl (the thumbnail yt-dlp gave), else
     * from YouTube's standard thumbnail, which every video has. Null if neither could be read.
     */
    public function localize(?string $remoteUrl, string $videoId): ?string
    {
        $candidates = array_unique(array_filter([$remoteUrl, 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg']));

        foreach ($candidates as $url) {
            if (null !== $local = $this->download($url, $videoId)) {
                return $local;
            }
        }

        return null;
    }

    // The YouTube id of a video page (watch?v=, youtu.be/) or of one of its thumbnails
    // (i.ytimg.com/vi/ID/…, vi_webp/ID/…).
    public static function videoIdFrom(string $url): ?string
    {
        return 1 === preg_match('#(?:[?&]v=|youtu\.be/|/vi(?:_webp)?/)([\w-]{11})#', $url, $matches) ? $matches[1] : null;
    }

    public function download(string $sourceUrl, string $videoId): ?string
    {
        if (!is_dir($this->coversDir))
        {
            mkdir($this->coversDir, 0775, true);
        }

        $process = new Process([
            'ffmpeg', '-v', 'quiet',
            '-y', '-i', $sourceUrl,
            '-vf', 'crop=min(iw\,ih):min(iw\,ih),scale=400:400',
            '-frames:v', '1',
            '-q:v', '4',
            $this->coversDir . '/' . $videoId . '.jpg'
        ]);

        $process->setTimeout(30);

        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        return '/covers/'.$videoId.'.jpg';
    }
}