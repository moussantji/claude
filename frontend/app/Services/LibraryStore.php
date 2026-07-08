<?php

namespace App\Services;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

/**
 * Writes anime-sama data into the normalized tables
 * (animes / seasons / episodes / episode_links).
 *
 * All operations are idempotent upserts keyed by URL (anime, season) or
 * (season, index) for episodes; an episode's links are fully replaced on each
 * sync so they always reflect the current mirrors.
 */
class LibraryStore
{
    /**
     * Upsert an anime from a catalogue summary (all-catalogues / search item).
     *
     * @param  array<string, mixed>  $summary
     */
    public function upsertAnimeFromSummary(array $summary): ?Anime
    {
        $url = $summary['url'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }

        return Anime::updateOrCreate(
            ['url' => $url],
            array_filter([
                'slug' => $this->slug($url),
                'name' => $summary['name'] ?? $this->slug($url),
                'alternative_names' => $summary['alternative_names'] ?? [],
                'genres' => $summary['genres'] ?? [],
                'categories' => $summary['categories'] ?? [],
                'languages' => $summary['languages'] ?? [],
                'image_url' => $summary['image_url'] ?? null,
                'last_synced_at' => now(),
            ], fn ($v) => $v !== null),
        );
    }

    /**
     * Upsert an anime and its seasons from a catalogue detail payload.
     *
     * @param  array<string, mixed>  $detail
     */
    public function upsertAnimeFromDetail(string $url, array $detail): Anime
    {
        $anime = Anime::updateOrCreate(
            ['url' => $url],
            [
                'slug' => $this->slug($url),
                'name' => $detail['name'] ?? $this->slug($url),
                'alternative_names' => $detail['alternative_names'] ?? [],
                'genres' => $detail['genres'] ?? [],
                'categories' => $detail['categories'] ?? [],
                'languages' => $detail['languages'] ?? [],
                'image_url' => $detail['image_url'] ?? null,
                'synopsis' => $detail['synopsis'] ?? null,
                'is_mature' => (bool) ($detail['is_mature'] ?? false),
                'last_synced_at' => now(),
            ],
        );

        foreach (array_values($detail['seasons'] ?? []) as $position => $season) {
            if (! empty($season['url'])) {
                Season::updateOrCreate(
                    ['url' => $season['url']],
                    [
                        'anime_id' => $anime->id,
                        'name' => $season['name'] ?? 'Saison',
                        'position' => $position,
                        'last_synced_at' => now(),
                    ],
                );
            }
        }

        return $anime;
    }

    /**
     * Sync a season's episodes and their video links.
     *
     * @param  array<int, array<string, mixed>>  $episodes
     * @return array{episodes: int, links: int}
     */
    public function syncEpisodes(Season $season, array $episodes): array
    {
        $episodeCount = 0;
        $linkCount = 0;

        foreach ($episodes as $ep) {
            $index = (int) ($ep['index'] ?? 0);
            if ($index <= 0) {
                continue;
            }

            $episode = Episode::updateOrCreate(
                ['season_id' => $season->id, 'index' => $index],
                [
                    'name' => $ep['name'] ?? null,
                    'long_name' => $ep['long_name'] ?? null,
                    'short_name' => $ep['short_name'] ?? null,
                    'last_synced_at' => now(),
                ],
            );
            $episodeCount++;

            // Replace links so they always reflect the current mirrors.
            $rows = [];
            $position = 0;
            foreach ((array) ($ep['languages'] ?? []) as $language => $groups) {
                foreach ((array) $groups as $group) {
                    foreach ((array) $group as $url) {
                        if (! is_string($url) || $url === '') {
                            continue;
                        }
                        $rows[] = [
                            'episode_id' => $episode->id,
                            'language' => (string) $language,
                            'position' => $position++,
                            'url' => $url,
                            'host' => $this->host($url),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            DB::transaction(function () use ($episode, $rows, &$linkCount) {
                EpisodeLink::where('episode_id', $episode->id)->delete();
                if ($rows !== []) {
                    EpisodeLink::insert($rows);
                    $linkCount += count($rows);
                }
            });
        }

        $season->forceFill(['last_synced_at' => now()])->save();

        return ['episodes' => $episodeCount, 'links' => $linkCount];
    }

    private function slug(string $url): string
    {
        $parts = array_values(array_filter(explode('/', $url)));

        return $parts !== [] ? end($parts) : $url;
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }
}
