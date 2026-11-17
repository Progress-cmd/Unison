<?php

namespace App\Admin\EventListener;

use App\Admin\Enum\ErrorSource;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener]
class ServerErrorListener
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // 404, 403…: a page that doesn't exist or isn't allowed, not a breakdown.
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return;
        }

        $request = $event->getRequest();

        try {
            $this->connection->insert('error_report', [
                'source' => ErrorSource::Server->value,
                'message' => sprintf('%s: %s (%s:%d)', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()),
                'context' => mb_substr($request->getMethod().' '.$request->getRequestUri(), 0, 255),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // The database is down too: the error page must still show.
        }
    }
}
