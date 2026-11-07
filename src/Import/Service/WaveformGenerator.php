<?php

namespace App\Import\Service;

use Symfony\Component\Process\Process;

class WaveformGenerator
{
    private const POINTS = 120;
    
    public function generate(string $filePath): ?string
    {
        $process = new Process([
            'ffmpeg', '-v', 'quiet',
            '-i', $filePath,
            '-ac', '1',
            '-filter:a',
            'aresample=4000',
            '-map', '0:a',
            '-c:a', 'pcm_s16le', '-f', 'data',
            '-'
        ]);
        
        $process->setTimeout(120);

        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();

        $samples = intdiv(strlen($output), 2);

        $sliceSize = intdiv($samples, self::POINTS);

        if ($sliceSize < 1) {
            return null;
        }

        $peaks = [];

        for ($i = 0; $i < self::POINTS; $i++) {
            $extract = substr($output, $i * $sliceSize * 2, $sliceSize * 2);
            $values = unpack('s*', $extract) ?: [];
            $peak = 0;

            foreach ($values as $value) {
                if (abs($value) > $peak) {
                    $peak = abs($value);
                }
            }

            array_push($peaks, $peak);
        }

        $max = max(1, ...$peaks);

        $waveform = '';

        foreach ($peaks as $peak) {
            $waveform .= chr((int) round($peak / $max * 255));
        }

        return base64_encode($waveform);
    }
}