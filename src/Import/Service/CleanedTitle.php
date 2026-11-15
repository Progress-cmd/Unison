<?php

namespace App\Import\Service;

final readonly class CleanedTitle
{
    /** @param list<string> $featuring */
    public function __construct(
        public string $title,
        public array $featuring,
    ) {}
}