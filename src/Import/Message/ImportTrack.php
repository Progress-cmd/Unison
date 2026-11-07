<?php

namespace App\Import\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ImportTrack
{
    public function __construct(
        public string $url,
        public int $userId,
    ) {}
}