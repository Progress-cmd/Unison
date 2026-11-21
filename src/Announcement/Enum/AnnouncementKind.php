<?php

namespace App\Announcement\Enum;

enum AnnouncementKind: string
{
    case Feature = 'feature';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Nouveauté',
            self::Message => 'Message',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Feature => 'material-symbols:auto-awesome-outline',
            self::Message => 'material-symbols:campaign-outline',
        };
    }
}
