<?php

namespace App\Import\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Song search on YouTube Music, through the internal API of music.youtube.com (yt-dlp relies on the same one).
 * It is not an official API: if YouTube changes it, search() throws and the page shows its error message.
 */
class YouTubeMusicSearch
{
    private const ENDPOINT = 'https://music.youtube.com/youtubei/v1/search?prettyPrint=false';

    // The client the music.youtube.com website introduces itself as.
    // "en" keeps "feat." in the titles, which TitleCleaner understands ("fr" would say "avec").
    private const CLIENT = [
        'clientName' => 'WEB_REMIX',
        'clientVersion' => '1.20251101.01.00',
        'hl' => 'en',
        'gl' => 'FR',
    ];

    // The "Songs" filter of the search page: no videos, albums, artists or playlists.
    private const SONGS_FILTER = 'EgWKAQIIAWoMEA4QChADEAQQCRAF';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * @return list<array{id: string, url: string, title: string, artist: string, album: ?string, duration: int, thumbnail: ?string}>
     *
     * @throws \RuntimeException when YouTube Music doesn't answer, or answers in a shape we don't know
     */
    public function search(string $query, int $limit = 10): array
    {
        try {
            $data = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => [
                    'context' => ['client' => self::CLIENT],
                    'query' => $query,
                    'params' => self::SONGS_FILTER,
                ],
                'headers' => ['Origin' => 'https://music.youtube.com'],
                'timeout' => 5,
                'max_duration' => 5,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('YouTube Music ne répond pas.', previous: $e);
        }

        $sections = $data['contents']['tabbedSearchResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] ?? null;

        if (!is_array($sections)) {
            throw new \RuntimeException('YouTube Music a répondu dans un format inconnu.');
        }

        // The songs are in the "shelf" section. Another section may come first (a spelling suggestion…).
        $items = [];
        foreach ($sections as $section) {
            if (isset($section['musicShelfRenderer']['contents'])) {
                $items = $section['musicShelfRenderer']['contents'];
                break;
            }
        }

        $songs = [];
        foreach ($items as $item) {
            $song = $this->parseSong($item['musicResponsiveListItemRenderer'] ?? []);

            if (null !== $song) {
                $songs[] = $song;
            }

            if (count($songs) >= $limit) {
                break;
            }
        }

        return $songs;
    }

    private function parseSong(array $item): ?array
    {
        $id = $item['playlistItemData']['videoId'] ?? null;
        $title = $this->columnText($item, 0);

        // Second line of a result: "Artist, Other & Another • Album • 3:53".
        $details = explode(' • ', $this->columnText($item, 1));
        $duration = $this->toSeconds(end($details));

        if (null === $id || '' === $title || null === $duration) {
            return null;
        }

        array_pop($details);
        $thumbnails = $item['thumbnail']['musicThumbnailRenderer']['thumbnail']['thumbnails'] ?? [];

        return [
            'id' => $id,
            // Same shape as Track::sourceUrl, so the "already imported" check can compare them.
            'url' => 'https://www.youtube.com/watch?v='.$id,
            'title' => $title,
            'artist' => $details[0] ?? '',
            'album' => $details[1] ?? null,
            'duration' => $duration,
            // The largest one comes last (120 px, enough for a 48 px cover).
            'thumbnail' => $thumbnails ? end($thumbnails)['url'] : null,
        ];
    }

    // Each line of a result is cut into "runs" (one per link or separator): glue them back together.
    private function columnText(array $item, int $column): string
    {
        $runs = $item['flexColumns'][$column]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'] ?? [];

        return trim(implode('', array_column($runs, 'text')));
    }

    // "3:53" or "1:02:10" to seconds; null for anything else.
    private function toSeconds(string|false $text): ?int
    {
        if (false === $text || !preg_match('/^(?:(\d+):)?(\d+):(\d{2})$/', $text, $matches)) {
            return null;
        }

        return (int) $matches[1] * 3600 + (int) $matches[2] * 60 + (int) $matches[3];
    }
}
