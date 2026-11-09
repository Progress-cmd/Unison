<?php

namespace App\Import\Service;

use App\Import\Exception\VideoUnavailableException;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class YtDlpClient
{
    private const ERROR_MESSAGES = [
        '/sign in to confirm your age|age.?restricted|inappropriate for some users/i'
            => ["YouTube exige une vérification d'âge pour cette vidéo", true],
        '/private video/i'
            => ['Vidéo privée', true],
        '/members[- ]only|join this channel/i'
            => ['Réservée aux membres de la chaîne', true],
        '/removed by the uploader|account associated .* has been terminated/i'
            => ['Vidéo supprimée par son auteur', true],

        '/not available on this app|player response|failed to extract/i'
            => ['YouTube a changé son format : yt-dlp doit être mis à jour', false],

        '/requested format is not available|no video formats found|only images are available/i'
            => ["Ce titre n'expose aucun flux audio : l'ayant droit en bloque le "
            . "téléchargement. Cherchez une autre mise en ligne du morceau", true],

        '/video (is )?unavailable|is not available/i'
            => ['Vidéo indisponible', true],
        '/not available in your country|geo.?restricted|blocked it in your country/i'
            => ['Vidéo bloquée dans ce pays', true],
        '/copyright/i'
            => ["Retirée pour motif de droits d'auteur", true],
        '/this live event will begin|premieres in/i'
            => ["La diffusion n'a pas encore commencé", false],
        '/sign in to confirm.*not a bot|confirm you.?re not a bot/i'
            => ['YouTube demande une confirmation anti-robot', false],

        '/please sign in|use --cookies/i'
            => ['YouTube réserve cette vidéo aux comptes connectés — '
            . 'déposez des cookies sur le serveur', false],

        '/page needs to be reloaded/i'
            => ['Cookies périmés : YouTube ne reconnaît plus la session', false],

        '/http error 429|too many requests|rate.?limit|throttl/i'
            => ['YouTube limite les téléchargements (trop de requêtes d\'affilée) — '
            . 'patientez quelques minutes avant de relancer', false],

        '/http error 403|forbidden/i'
            => ['YouTube a refusé le téléchargement (403) — généralement temporaire, réessayez', false],

        '/unable to download|failed to resolve|connection|timed out|temporary failure/i'
            => ['Échec réseau pendant la récupération', false],
        '/unsupported url|is not a valid url/i'
            => ['Lien non reconnu par yt-dlp', true],
    ];

    public function __construct(
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
    ) {}

    public function fetchMetadata(string $url): array
    {
        $output = $this->run([
            '--dump-json', 
            '--skip-download', 
            '--no-playlist', 
            $url
        ]);

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function fetchPlaylist(string $url): array
    {
        $output = $this->run([
            '--flat-playlist', 
            '--dump-single-json', 
            '--yes-playlist', 
            $url
        ]);

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);  
    }

    public function downloadAudio(string $url): string
    {
        $fileName = $this->run([
            '-f', 'ba[ext=m4a][audio_channels<=2]/ba[ext=m4a]/ba/b', 
            '-x', '--audio-format', 'm4a', 
            '--no-playlist', '--no-overwrites', 
            '-P', $this->musicDir, 
            '-o', '%(id)s.%(ext)s', 
            '--print', 'after_move:filepath', 
            $url
        ]);

        return basename($fileName);
    }

    private function run(array $arguments, int $timeout = 600): string
    {
        $commonArguments = ['yt-dlp', '--js-runtimes', 'quickjs'];

        $process = new Process(array_merge($commonArguments, $arguments));

        $process->setTimeout($timeout);

        try {
            $process->mustRun();
        } catch (\RuntimeException $e) {
            throw $this->failure($process->getErrorOutput(), $e);
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            throw new \RuntimeException("yt-dlp n'a rien renvoyé pour cette URL");
        }

        return $output;
    }

    private function failure(string $stderr, \Throwable $previous): \RuntimeException
    {
        $errors = explode("\n", $stderr);
        $lastError = null;

        foreach ($errors as $error) {
            if (str_starts_with($error, "ERROR:")) {
                $lastError = $error;
            }
        }

        if (is_null($lastError)) {
            return new \RuntimeException("yt-dlp a échoué sans donner de raison : ".trim($stderr), previous: $previous);
        }

        foreach (self::ERROR_MESSAGES as $pattern => [$message, $permanent]) {
            if (preg_match($pattern, $lastError)) {
                return $permanent ? new VideoUnavailableException($message, previous: $previous) : new \RuntimeException($message, previous: $previous);
            }
        }

        return new \RuntimeException(preg_replace("/^ERROR: \[[^\]]+\] [^:]+: /", '', $lastError), previous: $previous);
    }
}
