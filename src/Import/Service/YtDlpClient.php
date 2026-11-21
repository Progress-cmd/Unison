<?php

namespace App\Import\Service;

use App\Import\Exception\VideoUnavailableException;
use App\Import\Exception\YouTubeRateLimitedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class YtDlpClient
{
    // Checked before ERROR_MESSAGES: when YouTube limits the session, yt-dlp says « Video
    // unavailable. This content isn't available, try again later. … rate-limited », which
    // isn't the video's fault.
    private const RATE_LIMITED = '/try again later|rate-limited|http error 429|too many requests/i';

    // How many times a track waits for the limit to lift before being given up (to Messenger's
    // own retries). 3 × 15 min stays under the hour after which the queue would hand the
    // message out again.
    private const RATE_LIMIT_PAUSES = 3;

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

        '/http error 403|forbidden/i'
            => ['YouTube a refusé le téléchargement (403) — généralement temporaire, réessayez', false],

        '/unable to download|failed to resolve|connection|timed out|temporary failure/i'
            => ['Échec réseau pendant la récupération', false],
        '/unsupported url|is not a valid url/i'
            => ['Lien non reconnu par yt-dlp', true],
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire('%env(resolve:MUSIC_DIR)%')]
        private readonly string $musicDir,
        // YouTube cookies (Netscape format, from a secondary account) for age-restricted videos.
        // Optional: without the file, yt-dlp works as before. yt-dlp rewrites the file, so it
        // must be writable by www-data.
        #[Autowire('%env(default::YTDLP_COOKIES)%')]
        private readonly ?string $cookiesFile = null,
        private readonly int $rateLimitPause = 15 * 60,
    ) {}

    // The version installed: its date (2026.10.26). Null if yt-dlp doesn't answer.
    public function version(): ?string
    {
        $process = new Process(['yt-dlp', '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

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
            // A pause of 10 to 20 s before each download, as yt-dlp advises (-t sleep): a bulk
            // import then stays under YouTube's limit instead of running into it.
            '--sleep-interval', '10', '--max-sleep-interval', '20',
            '-P', $this->musicDir,
            '-o', '%(id)s.%(ext)s',
            '--print', 'after_move:filepath',
            $url
        ]);

        return basename($fileName);
    }

    private function run(array $arguments, int $timeout = 600): string
    {
        // --sleep-requests: a second between the requests of one extraction (yt-dlp's advice).
        $commonArguments = ['yt-dlp', '--js-runtimes', 'quickjs', '--sleep-requests', '1'];

        if ($this->cookiesFile && is_file($this->cookiesFile)) {
            array_push($commonArguments, '--cookies', $this->cookiesFile);
        }

        for ($pause = 1; ; ++$pause) {
            $process = new Process(array_merge($commonArguments, $arguments));
            $process->setTimeout($timeout);

            try {
                $process->mustRun();
                break;
            } catch (\RuntimeException $e) {
                $failure = $this->failure($process->getErrorOutput(), $e);

                if (!$failure instanceof YouTubeRateLimitedException || $pause > self::RATE_LIMIT_PAUSES) {
                    throw $failure;
                }

                // Otherwise every track left in the queue would fail one after the other in a
                // few minutes. The worker only imports: it waits, the queue stays as it is.
                $this->logger->error('YouTube limite les téléchargements : pause de {minutes} min ({pause}/{pauses}) avant de réessayer.', [
                    'minutes' => intdiv($this->rateLimitPause, 60),
                    'pause' => $pause,
                    'pauses' => self::RATE_LIMIT_PAUSES,
                ]);
                sleep($this->rateLimitPause);
            }
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

        if (preg_match(self::RATE_LIMITED, $lastError)) {
            return new YouTubeRateLimitedException(
                'YouTube limite les téléchargements (trop de requêtes d\'affilée) : nouvel essai automatique plus tard',
                previous: $previous,
            );
        }

        foreach (self::ERROR_MESSAGES as $pattern => [$message, $permanent]) {
            if (preg_match($pattern, $lastError)) {
                return $permanent ? new VideoUnavailableException($message, previous: $previous) : new \RuntimeException($message, previous: $previous);
            }
        }

        return new \RuntimeException(preg_replace("/^ERROR: \[[^\]]+\] [^:]+: /", '', $lastError), previous: $previous);
    }
}
