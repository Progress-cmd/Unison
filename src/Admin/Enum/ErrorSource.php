<?php

namespace App\Admin\Enum;

enum ErrorSource: string
{
    case Import = 'import';
    case YouTubeMusic = 'youtube_music';
    case Server = 'server';
}
