<?php

namespace App\Shared\Stats;

// Turns counts per hour, stored in UTC like every date of the application, into the series the
// dashboard draws, in French local time (as the dates Twig shows, config/packages/twig.yaml):
// a listen at 0:30 in Paris belongs to that day, not to the day before.
// Every period of the series is present, at 0 when nothing happened: a missing day would
// bring two distant dates together.
final class LocalCalendar
{
    public const TIMEZONE = 'Europe/Paris';

    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const SHORT_MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    private const SHORT_DAYS = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];

    // The four moments of « Mes écoutes »: with a few dozen listens, 24 columns show only noise,
    // « mostly in the evening » shows at once. Name, first hour, last hour + 1.
    private const MOMENTS = [['Nuit', 0, 6], ['Matin', 6, 12], ['Après-midi', 12, 18], ['Soir', 18, 24]];

    private \DateTimeZone $zone;

    public function __construct()
    {
        $this->zone = new \DateTimeZone(self::TIMEZONE);
    }

    // Start of the local day $days - 1 days ago, as a UTC date for the query.
    public function daysAgo(int $days): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today', $this->zone))->modify(sprintf('-%d days', $days - 1))->setTimezone(new \DateTimeZone('UTC'));
    }

    // Start of the local month $months - 1 months ago, as a UTC date for the query.
    public function monthsAgo(int $months): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('first day of this month midnight', $this->zone))->modify(sprintf('-%d months', $months - 1))->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @param array<string, int> $hourly UTC hour ('Y-m-d H:00:00') => count
     *
     * @param bool $weekdays labels by day name (lun., mar.…) rather than date: for a week
     *
     * @return list<array{label: string, title: string, value: int}>
     */
    public function lastDays(array $hourly, int $days, bool $weekdays = false): array
    {
        $totals = $this->fold($hourly, 'Y-m-d');
        $day = new \DateTimeImmutable('today', $this->zone);
        $series = [];

        for ($i = $days - 1; $i >= 0; --$i) {
            $date = $day->modify(sprintf('-%d days', $i));
            $weekday = $date->format('N') - 1;
            $series[] = [
                'label' => $weekdays ? self::SHORT_DAYS[$weekday] : $date->format('d/m'),
                'title' => sprintf('%s%d %s', $weekdays ? self::DAYS[$weekday].' ' : '', $date->format('j'), self::MONTHS[$date->format('n') - 1]),
                'value' => $totals[$date->format('Y-m-d')] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @param array<string, int> $hourly UTC hour ('Y-m-d H:00:00') => count
     *
     * @return list<array{label: string, title: string, value: int}>
     */
    public function hoursOfDay(array $hourly): array
    {
        $totals = $this->fold($hourly, 'G');
        $series = [];

        for ($hour = 0; $hour < 24; ++$hour) {
            $series[] = [
                'label' => $hour.' h',
                'title' => sprintf('De %d h à %d h', $hour, ($hour + 1) % 24),
                'value' => $totals[(string) $hour] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * The listens of the four moments of the day, in that order (night first).
     *
     * @param array<string, int> $hourly UTC hour ('Y-m-d H:00:00') => count
     *
     * @return list<array{name: string, detail: string, value: int}>
     */
    public function moments(array $hourly): array
    {
        $totals = $this->fold($hourly, 'G');

        return array_map(fn (array $moment) => [
            'name' => $moment[0],
            'detail' => sprintf('%d h – %d h', $moment[1], $moment[2]),
            'value' => array_sum(array_map(fn (int $hour) => $totals[(string) $hour] ?? 0, range($moment[1], $moment[2] - 1))),
        ], self::MOMENTS);
    }

    /**
     * @param array<string, int> $hourly UTC hour ('Y-m-d H:00:00') => count
     *
     * @return list<array{label: string, title: string, value: int}>
     */
    public function lastMonths(array $hourly, int $months): array
    {
        $totals = $this->fold($hourly, 'Y-m');
        $month = new \DateTimeImmutable('first day of this month midnight', $this->zone);
        $series = [];

        for ($i = $months - 1; $i >= 0; --$i) {
            $date = $month->modify(sprintf('-%d months', $i));
            $series[] = [
                'label' => self::SHORT_MONTHS[$date->format('n') - 1],
                'title' => self::MONTHS[$date->format('n') - 1].' '.$date->format('Y'),
                'value' => $totals[$date->format('Y-m')] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @param array<string, int> $hourly
     *
     * @return array<string, int> local period (formatted with $format) => count
     */
    private function fold(array $hourly, string $format): array
    {
        $totals = [];
        $utc = new \DateTimeZone('UTC');

        foreach ($hourly as $hour => $count) {
            $key = (new \DateTimeImmutable($hour, $utc))->setTimezone($this->zone)->format($format);
            $totals[$key] = ($totals[$key] ?? 0) + (int) $count;
        }

        return $totals;
    }
}
