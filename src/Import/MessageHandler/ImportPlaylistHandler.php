<?php

namespace App\Import\MessageHandler;

use App\Account\Repository\UserRepository;
use App\Import\Enum\ImportStatus;
use App\Import\Message\ImportPlaylist;
use App\Import\Repository\ImportJobRepository;
use App\Import\Service\PlaylistImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ImportPlaylistHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PlaylistImporter $playlistImporter,
        private readonly ImportJobRepository $importJobRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ImportPlaylist $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (null === $user) {
            throw new UnrecoverableMessageHandlingException("Utilisateur introuvable : ".$message->userId);
        }

        $title = $this->playlistImporter->import($message->url, $user);

        $job = null !== $message->jobId ? $this->importJobRepository->find($message->jobId) : null;

        $job?->setStatus(ImportStatus::Done);
        $job?->setTitle(mb_substr($title, 0, 255));

        $this->em->flush();
    }
}