<?php

namespace App\Import\Command;

use App\Import\Service\YtDlpClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:playlist', description: 'Imports a playlist from YouTube')]
final class ImportPlaylistCommand
{
    public function __construct(
        private readonly YtDlpClient $ytDlpClient,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $url,
    ): int {
        try {
            $playlist = $this->ytDlpClient->fetchPlaylist($url);

            $io->definitionList(
                ['Titre' => $playlist['title']],
                ['Nombre' => count($playlist['entries'])],
            );

            $io->listing(array_column($playlist['entries'], 'title'));

            return Command::SUCCESS;
        } catch (\RuntimeException|\JsonException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
