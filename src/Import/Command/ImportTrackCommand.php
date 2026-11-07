<?php

namespace App\Import\Command;

use App\Account\Repository\UserRepository;
use App\Import\Service\TrackImporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:track', description: 'Imports a track from YouTube')]
final class ImportTrackCommand
{
    public function __construct(
        private readonly TrackImporter $trackImporter,
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

            $track = $this->trackImporter->import($url, $user);

            $artists = $track->getArtists()->map(fn ($artist) => $artist->getName());
            $artists = $artists->toArray();
            $artists = implode(', ', $artists);

            $io->definitionList(
                ['Id' => $track->getId()],
                ['Titre' => $track->getTitle()],
                ['Artistes' => $artists],
            );

            return Command::SUCCESS;
        } catch (\RuntimeException|\JsonException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
