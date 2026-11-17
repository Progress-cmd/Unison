<?php

namespace App\Import\Service;

use App\Import\Entity\ImportJob;
use App\Import\Message\ImportPlaylist;
use App\Import\Message\ImportTrack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends an import job to the worker, as a playlist or as a single track depending on its link.
 */
class ImportLauncher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function launch(ImportJob $job): void
    {
        $userId = $job->getRequestedBy()->getId();

        if ($this->isPlaylist($job->getUrl())) {
            $this->messageBus->dispatch(new ImportPlaylist($job->getUrl(), $userId, $job->getId()));
        } else {
            $this->messageBus->dispatch(new ImportTrack($job->getUrl(), $userId, jobId: $job->getId()));
        }
    }

    private function isPlaylist(string $url): bool
    {
        return str_contains($url, '/playlist') || (str_contains($url, 'list=') && !str_contains($url, 'v='));
    }
}
