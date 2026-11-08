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

        $levels = [];

        for ($i = 0; $i < self::POINTS; $i++) {
            $extract = substr($output, $i * $sliceSize * 2, $sliceSize * 2);
            $values = unpack('s*', $extract) ?: [];
            $sum = 0;

            foreach ($values as $value) {
                $sum += $value * $value;
            }

            $levels[] = sqrt($sum / max(1, count($values)));
        }

        $max = max(1, ...$levels);

        $waveform = '';

        foreach ($levels as $level) {
            $waveform .= chr((int) round($level / $max * 255));
        }

        return base64_encode($waveform);
    }
}