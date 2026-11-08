<?php

namespace App\Import\MessageHandler;

use App\Account\Repository\UserRepository;
use App\Import\Message\ImportPlaylist;
use App\Import\Service\PlaylistImporter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class ImportPlaylistHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PlaylistImporter $playlistImporter,
    ) {}

    public function __invoke(ImportPlaylist $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (null === $user) {
            throw new UnrecoverableMessageHandlingException("Utilisateur introuvable : ".$message->userId);
        }

        $this->playlistImporter->import($message->url, $user);
    }
}