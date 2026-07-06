<?php

namespace App\Services\AnimeSama;

/**
 * A set of mirror player URLs for a single episode in a single language.
 *
 * Ported from the Python `Players` class: on creation it swaps the first two
 * players (anime-sama does this in `script_videos.js`) and rewrites the old
 * vidmoly.to host to vidmoly.net.
 */
final class Players
{
    /** @var list<string> */
    public array $urls;

    /**
     * @param  list<string>  $urls
     */
    public function __construct(array $urls)
    {
        $urls = array_values($urls);

        // swapPlayers(): swap the first two entries when there are at least two.
        if (count($urls) >= 2) {
            [$urls[0], $urls[1]] = [$urls[1], $urls[0]];
        }

        // Autofix old links.
        foreach ($urls as $i => $url) {
            $urls[$i] = str_replace('vidmoly.to', 'vidmoly.net', $url);
        }

        $this->urls = $urls;
    }

    public function isEmpty(): bool
    {
        return $this->urls === [];
    }

    /**
     * Return the player URLs with banned hosts removed and preferred hosts
     * moved to the front (stable for everything else).
     *
     * @param  list<string>  $preferPlayers  Preferred hostnames, best first.
     * @param  list<string>  $banPlayers     Hostnames to drop entirely.
     * @return list<string>
     */
    public function sortAndFilter(array $preferPlayers = [], array $banPlayers = []): array
    {
        $filtered = [];
        foreach ($this->urls as $index => $player) {
            $host = parse_url($player, PHP_URL_HOST);
            if (is_string($host) && in_array($host, $banPlayers, true)) {
                continue;
            }
            $filtered[] = ['index' => $index, 'player' => $player, 'host' => is_string($host) ? $host : null];
        }

        usort($filtered, function (array $a, array $b) use ($preferPlayers): int {
            return $this->rank($a['host'], $preferPlayers) <=> $this->rank($b['host'], $preferPlayers)
                ?: $a['index'] <=> $b['index'];
        });

        return array_map(static fn (array $entry): string => $entry['player'], $filtered);
    }

    /**
     * Sort key: preferred hosts get a negative rank (earlier), everything else 0.
     */
    private function rank(?string $host, array $preferPlayers): int
    {
        if ($host === null) {
            return 0;
        }

        $position = array_search($host, $preferPlayers, true);

        return $position === false ? 0 : $position - count($preferPlayers);
    }
}
