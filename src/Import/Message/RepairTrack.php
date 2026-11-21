<?php

namespace App\Import\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

// A track whose audio file or cover is missing (or still a link to YouTube): the worker fetches
// what's missing again (admin, Maintenance tab).
#[AsMessage('async')]
final readonly class RepairTrack
{
    public function __construct(
        public int $trackId,
        // For the Erreurs page, if it fails.
        public string $url,
    ) {}
}
