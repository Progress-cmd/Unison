<?php

namespace App\Import\EventListener;

use App\Import\Enum\ImportStatus;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use App\Import\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
class ImportFailureListener
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ImportTrack && !$message instanceof ImportPlaylist) {
            return;
        }
        $job = null !== $message->jobId ? $this->importJobRepository->find($message->jobId) : null;

        if (null === $job) {
            return;
        }
        $failure = $event->getThrowable()->getPrevious() ?? $event->getThrowable();

        $job->setStatus(ImportStatus::Failed);
        $job->setError(mb_substr($failure->getMessage(), 0, 255));

        $this->em->flush();
    }
}