<?php

namespace App\Import\Command;

use App\Account\Repository\UserRepository;
use App\Import\Message\ImportTrack;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:import:track', description: 'Imports a track from YouTube')]
final class ImportTrackCommand
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly UserRepository $userRepository,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $url,
        #[Argument] string $email,
    ): int {
        try {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (null === $user) {
                $io->error("Aucun utilisateur trouvé");

                return Command::FAILURE;
            }

            $this->messageBus->dispatch(new ImportTrack($url, $user->getId()));

            $io->success('Import ajouté à la file d\'attente.');

            return Command::SUCCESS;
        } catch (\RuntimeException|\JsonException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
