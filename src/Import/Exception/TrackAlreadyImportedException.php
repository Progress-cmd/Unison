<?php

namespace App\Import\Exception;

final class TrackAlreadyImportedException extends \RuntimeException
{
    public function __construct(public readonly string $trackTitle)
    {
        parent::__construct('Ce morceau est déjà dans la bibliothèque : '.$trackTitle);
    }
}
