<?php

namespace App\Admin\Maintenance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// The YouTube cookies yt-dlp uses for the videos that ask to sign in (age check…): the file at
// YTDLP_COOKIES (deploy/README.md), replaced or removed from the Maintenance tab. Exported from a
// browser of a secondary account, Netscape format.
final class CookiesFile
{
    public function __construct(
        #[Autowire('%env(default::YTDLP_COOKIES)%')]
        private readonly ?string $path = null,
    ) {}

    /**
     * @return array{configured: bool, present: bool, modifiedAt: ?\DateTimeImmutable, cookies: int}
     */
    public function status(): array
    {
        $present = $this->path && is_file($this->path);

        return [
            'configured' => (bool) $this->path,
            'present' => $present,
            'modifiedAt' => $present ? (new \DateTimeImmutable())->setTimestamp(filemtime($this->path)) : null,
            'cookies' => $present ? count($this->cookieLines((string) file_get_contents($this->path))) : 0,
        ];
    }

    /**
     * Puts the file sent in place, readable by the application only.
     *
     * @throws \InvalidArgumentException not a cookies file (the reason, in French)
     */
    public function replace(UploadedFile $file): int
    {
        if (!$this->path) {
            throw new \InvalidArgumentException('YTDLP_COOKIES n\'est pas définie sur ce serveur.');
        }

        $content = (string) file_get_contents($file->getPathname());
        $cookies = $this->cookieLines($content);

        if (!str_contains($content, 'HTTP Cookie File') && !$cookies) {
            throw new \InvalidArgumentException('Ce n\'est pas un fichier de cookies au format Netscape (cookies.txt).');
        }

        if (!array_filter($cookies, fn (array $cookie) => str_contains($cookie[0], 'youtube.com'))) {
            throw new \InvalidArgumentException('Aucun cookie de youtube.com dans ce fichier.');
        }

        // Written next to it, then moved: yt-dlp never reads half a file.
        $temporary = $this->path.'.new';
        file_put_contents($temporary, $content);
        chmod($temporary, 0600);
        rename($temporary, $this->path);

        return count($cookies);
    }

    public function remove(): void
    {
        if ($this->path && is_file($this->path)) {
            unlink($this->path);
        }
    }

    // The cookie lines of a Netscape file: 7 fields separated by tabs (« #HttpOnly_ » included).
    /**
     * @return list<list<string>>
     */
    private function cookieLines(string $content): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $content) as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', $line);

            if ('' === trim($line) || str_starts_with($line, '#')) {
                continue;
            }

            $fields = explode("\t", $line);

            if (7 === count($fields)) {
                $lines[] = $fields;
            }
        }

        return $lines;
    }
}
