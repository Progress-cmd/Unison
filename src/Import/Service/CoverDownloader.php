<?php

namespace App\Import\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

class CoverDownloader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/covers')]
        private readonly string $coversDir,
    ) {}

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