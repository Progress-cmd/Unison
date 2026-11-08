<?php

namespace App\Shared\Twig;

use Twig\Attribute\AsTwigFilter;

final class DurationExtension
{
    #[AsTwigFilter('duration')]
    public function format(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}