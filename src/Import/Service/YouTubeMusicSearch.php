<?php

namespace App\Import\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Song and album search on YouTube Music, through the internal API of music.youtube.com (yt-dlp relies on the same one).
 * It is not an official API: if YouTube changes it, the search methods throw and the page shows its error message.
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

    // The "Songs" and "Albums" filters of the search page.
    private const SONGS_FILTER = 'EgWKAQIIAWoMEA4QChADEAQQCRAF';
    private const ALBUMS_FILTER = 'EgWKAQIYAWoMEA4QChADEAQQCRAF';

    // Words YouTube Music puts before the artist on an album line.
    private const ALBUM_TYPES = ['Album', 'Single', 'EP'];

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
        return $this->collect($this->request($query, self::SONGS_FILTER), $this->parseSong(...), $limit);
    }

    /**
     * @return list<array{id: string, url: string, title: string, artist: string, type: string, year: ?string, thumbnail: ?string}>
     *
     * @throws \RuntimeException when YouTube Music doesn't answer, or answers in a shape we don't know
     */
    public function searchAlbums(string $query, int $limit = 4): array
    {
        return $this->collect($this->request($query, self::ALBUMS_FILTER), $this->parseAlbum(...), $limit);
    }

    /**
     * Sends the search and returns the raw result rows.
     */
    private function request(string $query, string $filter): array
    {
        try {
            $data = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => [
                    'context' => ['client' => self::CLIENT],
                    'query' => $query,
                    'params' => $filter,
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

        // The results are in the "shelf" section. Another section may come first (a spelling suggestion…).
        foreach ($sections as $section) {
            if (isset($section['musicShelfRenderer']['contents'])) {
                return $section['musicShelfRenderer']['contents'];
            }
        }

        return [];
    }

    /**
     * Turns the rows into results with $parse, skipping the rows it can't read, up to $limit.
     */
    private function collect(array $rows, callable $parse, int $limit): array
    {
        $results = [];

        foreach ($rows as $row) {
            $result = $parse($row['musicResponsiveListItemRenderer'] ?? []);

            if (null !== $result) {
                $results[] = $result;
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function parseSong(array $item): ?array
    {
        $id = $item['playlistItemData']['videoId'] ?? null;
        $title = $this->columnText($item, 0);

        // Second line of a song: "Artist, Other & Another • Album • 3:53".
        $details = explode(' • ', $this->columnText($item, 1));
        $duration = $this->toSeconds(end($details));

        if (null === $id || '' === $title || null === $duration) {
            return null;
        }

        array_pop($details);

        return [
            'id' => $id,
            // Same shape as Track::sourceUrl, so the "already imported" check can compare them.
            'url' => 'https://www.youtube.com/watch?v='.$id,
            'title' => $title,
            'artist' => $details[0] ?? '',
            'album' => $details[1] ?? null,
            'duration' => $duration,
            // Not the YouTube Music picture: yt3.googleusercontent.com answers 429 (too many requests)
            // when a page asks for a dozen at once. i.ytimg.com doesn't. Its 320×180 frame holds the
            // square cover in the middle, which the 48 px .track-cover (object-fit: cover) keeps.
            'thumbnail' => 'https://i.ytimg.com/vi/'.$id.'/mqdefault.jpg',
        ];
    }

    private function parseAlbum(array $item): ?array
    {
        // The playlist that holds the album's tracks, behind the play button drawn over the cover.
        $id = $item['overlay']['musicItemThumbnailOverlayRenderer']['content']['musicPlayButtonRenderer']['playNavigationEndpoint']['watchPlaylistEndpoint']['playlistId'] ?? null;
        $title = $this->columnText($item, 0);

        if (null === $id || '' === $title) {
            return null;
        }

        // Second line of an album: "Album • Artist • 2013" (or "Single", "EP").
        $details = explode(' • ', $this->columnText($item, 1));
        $type = in_array($details[0], self::ALBUM_TYPES, true) ? array_shift($details) : 'Album';
        $year = preg_match('/^\d{4}$/', (string) end($details)) ? array_pop($details) : null;

        return [
            'id' => $id,
            // Same shape as Album::sourceUrl (the playlist's webpage_url from yt-dlp).
            'url' => 'https://www.youtube.com/playlist?list='.$id,
            'title' => $title,
            'artist' => $details[0] ?? '',
            'type' => $type,
            'year' => $year,
            'thumbnail' => $this->thumbnail($item),
        ];
    }

    // Each line of a result is cut into "runs" (one per link or separator): glue them back together.
    private function columnText(array $item, int $column): string
    {
        $runs = $item['flexColumns'][$column]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'] ?? [];

        return trim(implode('', array_column($runs, 'text')));
    }

    // The smallest picture still sharp on a 48 px cover (albums offer 60 up to 544 px).
    // Albums have no video id, so they keep the YouTube Music picture: only a few per page.
    private function thumbnail(array $item): ?string
    {
        $thumbnails = $item['thumbnail']['musicThumbnailRenderer']['thumbnail']['thumbnails'] ?? [];

        foreach ($thumbnails as $thumbnail) {
            if (($thumbnail['width'] ?? 0) >= 96) {
                return $thumbnail['url'];
            }
        }

        return $thumbnails ? end($thumbnails)['url'] : null;
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
