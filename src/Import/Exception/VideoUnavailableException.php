<?php

namespace App\Import\Exception;

use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

final class VideoUnavailableException extends \RuntimeException implements UnrecoverableExceptionInterface
{

}
