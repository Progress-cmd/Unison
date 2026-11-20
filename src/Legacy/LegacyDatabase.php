<?php

namespace App\Legacy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// The database of the first Unison (v1), read once to bring its data into this one
// (app:legacy:import, then app:legacy:link). See deploy/README.md for how it gets loaded.
final class LegacyDatabase
{
    public function __construct(
        // Given only for the migration, e.g. mysql://root:…@db:3306/unison_v1
        #[Autowire('%env(default::LEGACY_DATABASE_URL)%')]
        private readonly ?string $url = null,
    ) {
    }

    public function connect(): Connection
    {
        if (!$this->url) {
            throw new \RuntimeException('LEGACY_DATABASE_URL n\'est pas définie : l\'adresse de la base de la v1 (voir deploy/README.md).');
        }

        $connection = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($this->url));

        // The v1 dates are TIMESTAMPs: read them in UTC, like this application stores its own.
        $connection->executeStatement("SET time_zone = '+00:00'");

        return $connection;
    }

    // The YouTube id of a v1 link (youtube.com/watch?v=, music.youtube.com, youtu.be/),
    // the same way TrackImporter recognises a track already in the library.
    public static function videoId(string $url): ?string
    {
        return 1 === preg_match('#(?:[?&]v=|youtu\.be/)([\w-]{11})#', $url, $matches) ? $matches[1] : null;
    }

    // The address this application stores in Track::sourceUrl for a YouTube id.
    public static function sourceUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v='.$videoId;
    }
}
