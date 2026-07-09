<?php

namespace App\Console\Commands;

use App\Models\AnimeCache;
use Illuminate\Console\Command;

/**
 * List the stored video player links (episode mirrors) from the database.
 *
 *   php artisan anime:links                       # all stored links (up to --limit)
 *   php artisan anime:links --serie=naruto        # filter by serie/url substring
 *   php artisan anime:links --lang=VF             # only one language
 *   php artisan anime:links --json                # machine-readable export
 */
class ListAnimeLinks extends Command
{
    protected $signature = 'anime:links
        {--serie= : Filter by serie name or catalogue/season URL substring}
        {--lang= : Only this language, e.g. VF, VOSTFR}
        {--limit=200 : Maximum number of links to output}
        {--json : Output as JSON instead of a table}';

    protected $description = 'List the stored episode video/player links from the database.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $langFilter = mb_strtoupper((string) $this->option('lang'));
        $serie = trim((string) $this->option('serie'));

        $query = AnimeCache::query()->where('type', 'episodes');
        if ($serie !== '') {
            $query->where('cache_key', 'like', '%'.$serie.'%');
        }

        $links = [];
        $reached = false;

        foreach ($query->orderBy('cache_key')->get() as $row) {
            if ($reached) {
                break;
            }
            $episodes = $row->payload;
            if (! is_array($episodes)) {
                continue;
            }

            foreach ($episodes as $episode) {
                if ($reached) {
                    break;
                }
                if (! is_array($episode)) {
                    continue;
                }

                $serieName = trim((string) ($episode['serie_name'] ?? ''));
                $seasonName = trim((string) ($episode['season_name'] ?? ''));
                $index = $episode['index'] ?? '?';

                foreach ((array) ($episode['languages'] ?? []) as $lang => $groups) {
                    if ($reached) {
                        break;
                    }
                    if ($langFilter !== '' && mb_strtoupper((string) $lang) !== $langFilter) {
                        continue;
                    }

                    foreach ((array) $groups as $group) {
                        foreach ((array) $group as $url) {
                            $links[] = [
                                'serie' => $serieName,
                                'season' => $seasonName,
                                'episode' => $index,
                                'language' => $lang,
                                'url' => $url,
                            ];

                            if (count($links) >= $limit) {
                                $reached = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (! $links) {
            $this->warn('No video links stored yet. Run `php artisan anime:ingest --full` first.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $links,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Serie', 'Season', 'Ep', 'Lang', 'Link'],
            array_map(fn ($l) => [
                $l['serie'],
                $l['season'],
                $l['episode'],
                $l['language'],
                $l['url'],
            ], $links)
        );

        $this->line(count($links).' link(s) shown'.($reached ? " (capped at --limit={$limit})" : '').'.');

        return self::SUCCESS;
    }
}
