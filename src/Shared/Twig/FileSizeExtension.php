<?php

namespace App\Shared\Twig;

use Twig\Attribute\AsTwigFilter;

final class FileSizeExtension
{
    private const UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

    /**
     * A size in bytes, in French units: ['value' => '2,4', 'unit' => 'Go']. Apart, so the unit
     * can be drawn smaller (dashboard figures).
     *
     * @return array{value: string, unit: string}
     */
    #[AsTwigFilter('file_size')]
    public function format(int $bytes): array
    {
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1) : 0;
        $value = $bytes / 1024 ** $power;

        return [
            // One decimal below 10 (2,4 Go), none above (850 Mo).
            'value' => number_format($value, $power > 0 && $value < 10 ? 1 : 0, ',', ' '),
            'unit' => self::UNITS[$power],
        ];
    }
}
