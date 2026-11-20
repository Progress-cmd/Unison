<?php

namespace App\Admin\Enum;

enum ErrorSource: string
{
    case Import = 'import';
    case YouTubeMusic = 'youtube_music';
    case Server = 'server';
    // JavaScript errors of the visitors' browsers (assets/error_reporter.js).
    case Browser = 'browser';
}
