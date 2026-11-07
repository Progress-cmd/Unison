<?php

namespace App\Import\Service;

use Symfony\Component\Process\Process;

class YtDlpClient
{
    public function fetchMetadata(string $url): array
    {
        // Point d'entré vers yt-dlp
        $process = new Process(['yt-dlp', '--dump-json', '--skip-download', '--no-playlist', '--js-runtimes', 'quickjs', $url]);

        try {
            $process->mustRun();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($process->getErrorOutput(), previous:$e);
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            throw new \RuntimeException("Aucune vidéo trouvée pour cette URL");
        }
        
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}