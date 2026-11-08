<?php

namespace App\Import\Service;

use App\Account\Entity\User;
use App\Import\Exception\TrackAlreadyImportedException;
use App\Library\Entity\Album;
use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Repository\TrackRepository;
use App\Library\Repository\ArtistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TrackImporter
{
    public function __construct(
        private readonly YtDlpClient $ytDlpClient,
        private readonly WaveformGenerator $waveformGenerator,
        private readonly TrackRepository $trackRepository,
        private readonly ArtistRepository $artistRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
    ) {}

    public function import(string $url, User $user, ?Album $album = null, ?int $trackNumber = null): Track
    {
        $metadata = $this->ytDlpClient->fetchMetadata($url);
        $title = $metadata['track'] ?? $metadata['title'];

        $existing = $this->trackRepository->findOneBy(['sourceUrl' => $metadata['webpage_url']]);

        if (null !== $existing) {
            if (null === $album) {
                throw new TrackAlreadyImportedException("Ce morceau est déjà dans la bibliothèque : ".$title);
            }

            $existing->setAlbum($album);
            $existing->setTrackNumber($trackNumber);

            $this->completeAlbum($album, $existing, $metadata);

            $this->em->flush();

            return $existing;
        }

        $fileName = $this->ytDlpClient->downloadAudio($metadata['webpage_url']);
        $waveform = $this->waveformGenerator->generate($this->musicDir . '/' . $fileName);

        $track = new Track();
        $track->setTitle(mb_substr($title, 0, 255));
        $track->setDuration((int) $metadata['duration']);
        $track->setWaveform($waveform);
        $track->setFileName($fileName);
        $track->setSourceUrl($metadata['webpage_url']);
        $track->setCover($metadata['thumbnail'] ?? null);
        $track->setAddedBy($user);
        $track->setAlbum($album);
        $track->setSourceAlbum($album);
        $track->setTrackNumber($trackNumber);

        foreach ($this->artistNames($metadata) as $name) {
            $artist = $this->artistRepository->findOneBy(['name' => $name]);

            if (null === $artist) {
                $artist = new Artist();

                $artist->setName($name);
                $this->em->persist($artist);
            }

            $track->addArtist($artist);
        }

        if (null !== $album) {
            $this->completeAlbum($album, $track, $metadata);
        }

        $this->em->persist($track);
        $this->em->flush();

        return $track;
    }

    private function artistNames(array $metadata): array
    {
        if (isset($metadata['artists'])) {
            $names = $metadata['artists'];
        } elseif (isset($metadata['artist'])) {
            $names = explode(',', $metadata['artist']);
        } else {
            $names = [preg_replace('/\s*-\s*Topic$/i', '', $metadata['channel'] ?? '')];
        }

        $names = array_map('trim', $names);
        $names = array_filter($names);
        $names = array_map(fn ($name) => mb_substr($name, 0, 100), $names);
        $names = array_unique($names);
        $names = array_values($names);

        return $names;
    }

    private function completeAlbum(Album $album, Track $track, array $metadata): void
    {
        if (null === $album->getCover()) {
            $album->setCover($track->getCover());
        }

        if (null === $album->getYear() && isset($metadata['release_year'])) {
            $album->setYear((int) $metadata['release_year']);
        }

        foreach ($track->getArtists() as $artist) {
            $album->addArtist($artist);
        }
    }
}