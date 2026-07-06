<?php

namespace App\Services\AnimeSama;

/**
 * A single episode with its players grouped by language id.
 *
 * Ported from the Python `Episode` / `Languages` classes.
 */
final class Episode
{
    /**
     * @param  array<string, Players>  $languages  langId => Players
     */
    public function __construct(
        public array $languages,
        public string $serieName,
        public string $seasonName,
        public string $rawName,
        public int $index,
    ) {
    }

    public function name(): string
    {
        return trim($this->rawName);
    }

    public function seasonNumber(): int
    {
        if (preg_match('/\d+/', $this->seasonName, $m) === 1) {
            return (int) $m[0];
        }

        return 0;
    }

    public function longName(): string
    {
        return "{$this->seasonName} - {$this->name()}";
    }

    public function shortName(): string
    {
        return sprintf('%s S%02dE%02d', $this->serieName, $this->seasonNumber(), $this->index);
    }

    /**
     * Group players by human language, preserving insertion order.
     *
     * @return array<string, list<Players>>
     */
    public function availables(): array
    {
        $availables = [];
        foreach ($this->languages as $langId => $players) {
            $lang = Lang::idToLang($langId);
            $availables[$lang][] = $players;
        }

        return $availables;
    }

    /**
     * The first playable URL following the preferred language order, or null.
     *
     * @param  list<string>  $preferLanguages
     * @param  list<string>  $preferPlayers
     * @param  list<string>  $banPlayers
     */
    public function best(array $preferLanguages, array $preferPlayers = [], array $banPlayers = []): ?string
    {
        $availables = $this->availables();

        foreach ($preferLanguages as $language) {
            foreach ($availables[$language] ?? [] as $players) {
                foreach ($players->sortAndFilter($preferPlayers, $banPlayers) as $url) {
                    return $url;
                }
            }
        }

        // Fallback: any language (Python iterates lang2ids order).
        foreach (array_keys(Lang::LANG_TO_IDS) as $language) {
            foreach ($availables[$language] ?? [] as $players) {
                foreach ($players->sortAndFilter($preferPlayers, $banPlayers) as $url) {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * Serialise to the JSON shape returned by the API.
     *
     * @param  list<string>  $preferLanguages
     * @return array<string, mixed>
     */
    public function toArray(array $preferLanguages): array
    {
        $languages = [];
        foreach ($this->availables() as $lang => $groups) {
            $languages[$lang] = array_map(
                static fn (Players $players): array => $players->urls,
                $groups
            );
        }

        return [
            'index' => $this->index,
            'name' => $this->name(),
            'season_name' => $this->seasonName,
            'serie_name' => $this->serieName,
            'long_name' => $this->longName(),
            'short_name' => $this->shortName(),
            'languages' => $languages,
            'available_languages' => array_keys($languages),
            'best_player' => $this->best($preferLanguages),
        ];
    }
}
