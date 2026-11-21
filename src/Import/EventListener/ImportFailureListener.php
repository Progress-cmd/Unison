<?php

namespace App\Import\EventListener;

use App\Admin\Entity\ErrorReport;
use App\Admin\Enum\ErrorSource;
use App\Import\Enum\ImportStatus;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use App\Import\Message\RepairTrack;
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
        $failure = $event->getThrowable()->getPrevious() ?? $event->getThrowable();

        // A repair from the Maintenance tab: no import behind it, the card says which track.
        if ($message instanceof RepairTrack) {
            $this->em->persist(new ErrorReport(ErrorSource::Import, 'Réparation impossible : '.$failure->getMessage(), $message->url, null));
            $this->em->flush();

            return;
        }

        if (!$message instanceof ImportTrack && !$message instanceof ImportPlaylist) {
            return;
        }
        $job = null !== $message->jobId ? $this->importJobRepository->find($message->jobId) : null;

        if (null !== $job) {
            $job->setStatus(ImportStatus::Failed);
            $job->setError(mb_substr($failure->getMessage(), 0, 255));
        }

        $this->em->persist(new ErrorReport(ErrorSource::Import, $failure->getMessage(), $message->url, $job));

        $this->em->flush();
    }
}
