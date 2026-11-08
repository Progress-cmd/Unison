<?php

namespace App\Import\MessageHandler;

use App\Account\Repository\UserRepository;
use App\Import\Exception\TrackAlreadyImportedException;
use App\Import\Message\ImportTrack;
use App\Import\Service\TrackImporter;
use App\Library\Repository\AlbumRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ImportTrackHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TrackImporter $trackImporter,
        private readonly AlbumRepository $albumRepository,
    ) {}

    public function __invoke(ImportTrack $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (null === $user) {
            throw new UnrecoverableMessageHandlingException("Utilisateur introuvable : ".$message->userId);
        }

        $album = null;

        if (null !== $message->albumId) {
            $album = $this->albumRepository->find($message->albumId);

            if (null === $album) {
                throw new UnrecoverableMessageHandlingException("Album introuvable : ".$message->albumId);
            }
        }

        try {
            $this->trackImporter->import($message->url, $user, $album, $message->trackNumber);
        } catch (TrackAlreadyImportedException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }
    }
}