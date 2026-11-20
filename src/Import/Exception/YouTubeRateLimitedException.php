<?php

namespace App\Import\Exception;

// YouTube refuses everything for a while (up to an hour) after too many downloads in a row.
// Not about the video: worth trying again later.
final class YouTubeRateLimitedException extends \RuntimeException
{
}
