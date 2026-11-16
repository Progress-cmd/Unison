<?php

namespace App\Import\Service;

final class TitleCleaner
{
    private const REMASTER = '/\s*[\(\[](?:\d{4}\s+)?remaster(?:ed|isé)?(?:\s+(?:en\s+)?\d{4})?[\)\]]/iu';
    private const VERSION = '/\s*[\(\[](?:radio edit|radio mix|video edit|single edit|edit|single version\s*\/\s*remastered|single version|single mix|album version|original version(?:\s+\d{4})?)[\)\]]/iu';
    private const OFFICIAL = '/\s*[\(\[](?:official (?:music |lyric )?video(?: remastered)?|official audio|clip officiel|vidéo officielle|lyrics?|paroles|lyric video|audio|visualizer|tiktok song|hq sound|hq|hd)[\)\]]/iu';

    // "(feat. X)", "[ft. X]", "(with X)": the names are captured in group 1.
    private const FEATURING = '/\s*[\(\[](?:feat\.?|ft\.?|featuring|with)\s+([^\)\]]+)[\)\]]/iu';
    // "Title ft. X" without brackets: the names stop before a bracket or at the end.
    private const FEATURING_INLINE = '/\s+(?:feat\.|ft\.|featuring)\s+(.+?)(?=\s*[\(\[]|$)/iu';
    // Between two featured names: "X, Y & Z", "X and Y", "X et Y".
    private const NAME_SEPARATOR = '/\s*(?:,|&|\band\b|\bet\b)\s*/iu';

    // "Artist - Title": everything before the first dash with spaces around it.
    private const ARTIST_PREFIX = '/^(?<artist>.+?)\s+[-–—]\s+(?<title>.+)$/u';

    public function clean(string $title): CleanedTitle
    {
        $featuring = [];

        $title = preg_replace([self::REMASTER, self::VERSION, self::OFFICIAL], '', $title);

        foreach ([self::FEATURING, self::FEATURING_INLINE] as $pattern) {
            if(preg_match($pattern, $title, $matches)) {
                $title = str_replace($matches[0], '', $title);
                $names = preg_split(self::NAME_SEPARATOR, $matches[1]);
                $featuring = array_merge($featuring, $names);
            }
        }

        $featuring = array_map('trim', $featuring);
        $featuring = array_filter($featuring);
        $featuring = array_values($featuring);

        $title = trim($title);

        return new CleanedTitle($title, $featuring);
    }

    public function splitArtist(string $title): ?array
    {
        if (!preg_match(self::ARTIST_PREFIX, $title, $matches)) {
            return null;
        }

        return ['artist' => trim($matches['artist']), 'title' => $matches['title']];
    }
}
