<?php

namespace App\Console\Commands;

use App\Models\EpisodeLink;
use Illuminate\Console\Command;

/**
 * List video links from the normalized episode_links table.
 *
 *   php artisan library:links
 *   php artisan library:links --serie=naruto --lang=VF
 *   php artisan library:links --json
 */
class ListLibraryLinks extends Command
{
    protected $signature = 'library:links
        {--serie= : Filter by anime name or slug}
        {--lang= : Only this language, e.g. VF}
        {--limit=200 : Maximum links to output}
        {--json : Output as JSON}';

    protected $description = 'List stored video links from the episode_links table.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $lang = (string) $this->option('lang');
        $serie = trim((string) $this->option('serie'));

        $query = EpisodeLink::query()->with('episode.season.anime');

        if ($lang !== '') {
            $query->where('language', $lang);
        }
        if ($serie !== '') {
            $query->whereHas('episode.season.anime', function ($a) use ($serie) {
                $a->where('name', 'like', "%{$serie}%")->orWhere('slug', 'like', "%{$serie}%");
            });
        }

        $links = $query->limit($limit)->get()->map(function (EpisodeLink $l) {
            $anime = $l->episode?->season?->anime;

            return [
                'anime' => $anime?->name ?? '',
                'season' => $l->episode?->season?->name ?? '',
                'episode' => $l->episode?->index,
                'language' => $l->language,
                'host' => $l->host,
                'url' => $l->url,
            ];
        });

        if ($links->isEmpty()) {
            $this->warn('No video links in the library yet. Run: php artisan library:ingest --full');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($links->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(
            ['Anime', 'Season', 'Ep', 'Lang', 'Host', 'Link'],
            $links->map(fn ($l) => [$l['anime'], $l['season'], $l['episode'], $l['language'], $l['host'], $l['url']])->all()
        );
        $this->line($links->count().' link(s).');

        return self::SUCCESS;
    }
}
