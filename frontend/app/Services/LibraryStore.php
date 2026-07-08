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

        $attrs = [
            'slug' => $this->slug($url),
            'name' => $summary['name'] ?? $this->slug($url),
            'last_synced_at' => now(),
        ];
        // Never overwrite an existing value with an empty one; never touch
        // trailer_url (it is set manually via the admin area).
        $this->putIfNotEmpty($attrs, 'alternative_names', $summary['alternative_names'] ?? null);
        $this->putIfNotEmpty($attrs, 'genres', $summary['genres'] ?? null);
        $this->putIfNotEmpty($attrs, 'categories', $summary['categories'] ?? null);
        $this->putIfNotEmpty($attrs, 'languages', $summary['languages'] ?? null);
        $this->putIfNotEmpty($attrs, 'image_url', $summary['image_url'] ?? null);

        return Anime::updateOrCreate(['url' => $url], $attrs);
    }

    /**
     * Upsert an anime and its seasons from a catalogue detail payload.
     *
     * @param  array<string, mixed>  $detail
     */
    public function upsertAnimeFromDetail(string $url, array $detail): Anime
    {
        $attrs = [
            'slug' => $this->slug($url),
            'name' => $detail['name'] ?? $this->slug($url),
            'is_mature' => (bool) ($detail['is_mature'] ?? false),
            'last_synced_at' => now(),
        ];
        // Preserve manual/previous values when the scrape comes back empty, and
        // never overwrite trailer_url (managed in the admin area).
        $this->putIfNotEmpty($attrs, 'alternative_names', $detail['alternative_names'] ?? null);
        $this->putIfNotEmpty($attrs, 'genres', $detail['genres'] ?? null);
        $this->putIfNotEmpty($attrs, 'categories', $detail['categories'] ?? null);
        $this->putIfNotEmpty($attrs, 'languages', $detail['languages'] ?? null);
        $this->putIfNotEmpty($attrs, 'image_url', $detail['image_url'] ?? null);
        $this->putIfNotEmpty($attrs, 'synopsis', $detail['synopsis'] ?? null);

        $anime = Anime::updateOrCreate(['url' => $url], $attrs);

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

    /**
     * Add $key => $value to $attrs only when $value is non-empty, so a refresh
     * never clobbers an existing (or manually edited) value with a blank one.
     */
    private function putIfNotEmpty(array &$attrs, string $key, mixed $value): void
    {
        if (is_array($value)) {
            if ($value !== []) {
                $attrs[$key] = $value;
            }
        } elseif (is_string($value)) {
            if (trim($value) !== '') {
                $attrs[$key] = trim($value);
            }
        } elseif ($value !== null) {
            $attrs[$key] = $value;
        }
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
