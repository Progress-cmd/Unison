<?php

namespace App\Admin\Maintenance;

use App\Library\Entity\Album;
use App\Library\Entity\Track;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Compares the files on the disk with what the library expects (admin, Maintenance tab): tracks
// without their audio file, covers missing or still at YouTube, and files nothing uses any more.
final class MediaCheck
{
    // A file this recent may be a download in progress (yt-dlp writes it before the track is
    // saved): never called an orphan.
    private const RECENT = 3600;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
        #[Autowire('%kernel.project_dir%/public/covers')]
        private readonly string $coversDir,
    ) {}

    /**
     * @return array{
     *     missingAudio: list<Track>,
     *     brokenCovers: list<Track>,
     *     brokenAlbumCovers: list<Album>,
     *     orphanMusic: list<string>,
     *     orphanCovers: list<string>,
     *     orphanBytes: int,
     * }
     */
    public function report(): array
    {
        $tracks = $this->em->getRepository(Track::class)->findAll();
        $albums = $this->em->getRepository(Album::class)->findAll();
        $music = $this->files($this->musicDir);
        $covers = $this->files($this->coversDir);

        $usedMusic = array_flip(array_map(fn (Track $track) => $track->getFileName(), $tracks));
        $usedCovers = [];

        foreach ([...$tracks, ...$albums] as $item) {
            if (null !== $name = $this->coverName($item->getCover())) {
                $usedCovers[$name] = true;
            }
        }

        $orphanMusic = array_keys(array_filter($music, fn (array $file, string $name) => !isset($usedMusic[$name]) && !$file['recent'], ARRAY_FILTER_USE_BOTH));
        $orphanCovers = array_keys(array_filter($covers, fn (array $file, string $name) => !isset($usedCovers[$name]) && !$file['recent'], ARRAY_FILTER_USE_BOTH));

        return [
            'missingAudio' => array_values(array_filter($tracks, fn (Track $track) => !isset($music[$track->getFileName()]))),
            'brokenCovers' => array_values(array_filter($tracks, fn (Track $track) => !$this->coverIsHere($track->getCover(), $covers))),
            'brokenAlbumCovers' => array_values(array_filter($albums, fn (Album $album) => !$this->coverIsHere($album->getCover(), $covers))),
            'orphanMusic' => $orphanMusic,
            'orphanCovers' => $orphanCovers,
            'orphanBytes' => array_sum(array_map(fn (string $name) => $music[$name]['size'], $orphanMusic))
                + array_sum(array_map(fn (string $name) => $covers[$name]['size'], $orphanCovers)),
        ];
    }

    /**
     * Deletes the files nothing uses any more (checked again now).
     *
     * @return array{files: int, bytes: int}
     */
    public function deleteOrphans(): array
    {
        $report = $this->report();
        $deleted = 0;

        foreach ([[$this->musicDir, $report['orphanMusic']], [$this->coversDir, $report['orphanCovers']]] as [$dir, $names]) {
            foreach ($names as $name) {
                if (@unlink($dir.'/'.$name)) {
                    ++$deleted;
                }
            }
        }

        return ['files' => $deleted, 'bytes' => $report['orphanBytes']];
    }

    /**
     * The tracks to give to the worker (RepairTrack): audio file missing, cover missing or remote.
     *
     * @return list<Track>
     */
    public function tracksToRepair(array $report): array
    {
        $tracks = [];

        foreach ([...$report['missingAudio'], ...$report['brokenCovers']] as $track) {
            $tracks[$track->getId()] = $track;
        }

        return array_values($tracks);
    }

    // The files right in that folder (not the hidden ones, not yt-dlp's temporary files).
    /**
     * @return array<string, array{size: int, recent: bool}>
     */
    private function files(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];

        foreach (new \FilesystemIterator($dir) as $file) {
            $name = $file->getFilename();

            if (!$file->isFile() || str_starts_with($name, '.') || preg_match('/\.(part|ytdl|temp|tmp)$/', $name)) {
                continue;
            }

            $files[$name] = ['size' => $file->getSize(), 'recent' => $file->getMTime() > time() - self::RECENT];
        }

        return $files;
    }

    // « /covers/abc.jpg » → « abc.jpg »; null for a link to YouTube or no cover.
    private function coverName(?string $cover): ?string
    {
        return null !== $cover && str_starts_with($cover, '/covers/') ? substr($cover, 8) : null;
    }

    private function coverIsHere(?string $cover, array $covers): bool
    {
        $name = $this->coverName($cover);

        return null !== $name && isset($covers[$name]);
    }
}
