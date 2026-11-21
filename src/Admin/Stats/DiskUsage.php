<?php

namespace App\Admin\Stats;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

// What the music and the covers take on the disk, and what's left on it.
final class DiskUsage
{
    public function __construct(
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
        #[Autowire('%kernel.project_dir%/public/covers')]
        private readonly string $coversDir,
    ) {}

    /**
     * @return array{music: int, covers: int, free: ?int} bytes; free is null when the system won't say
     */
    public function measure(): array
    {
        $free = is_dir($this->musicDir) ? @disk_free_space($this->musicDir) : false;

        return [
            'music' => $this->size($this->musicDir),
            'covers' => $this->size($this->coversDir),
            'free' => false === $free ? null : (int) $free,
        ];
    }

    // A couple of thousand files: read on every visit, no need to keep it anywhere.
    private function size(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $bytes = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }
}
