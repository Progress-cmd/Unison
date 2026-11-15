<?php

namespace App\Account\Enum;

enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Système',
            self::Light => 'Clair',
            self::Dark => 'Sombre',
        };
    }

}
