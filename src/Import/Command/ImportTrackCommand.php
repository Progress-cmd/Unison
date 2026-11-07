<?php

namespace App\Import\Command;

use App\Import\Service\YtDlpClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(name: 'app:import:track', description: 'Import a music')]
final class ImportTrackCommand
{
    public function __construct(
        private readonly YtDlpClient $ytDlpClient
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $url,
    ): int {
        try {
            $metadata = $this->ytDlpClient->fetchMetadata($url);

            $io->definitionList(
                ['Id' => $metadata['id']],
                ['Url' => $metadata['webpage_url']],
                ['Titre' => $metadata['track'] ?? $metadata['title']],
                ['Artist' => implode(', ', $metadata['artists'] ?? [$metadata['artist'] ?? $metadata['channel']])],
                ['Durée' => $metadata['duration']],
                ['Pochette' => $metadata['thumbnail'] ?? '-']
            );

            $io->success('Metadata successfully extracted!');

            return Command::SUCCESS;
        } catch (\RuntimeException|\JsonException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
