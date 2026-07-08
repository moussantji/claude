<?php

namespace Tests\Feature;

use App\Models\EpisodeLink;
use App\Services\LibraryStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_upserts_anime_seasons_episodes_and_links(): void
    {
        $store = app(LibraryStore::class);

        $anime = $store->upsertAnimeFromDetail('https://anime-sama.fr/catalogue/naruto/', [
            'name' => 'Naruto',
            'alternative_names' => ['ナルト'],
            'seasons' => [
                ['url' => 'https://anime-sama.fr/catalogue/naruto/saison1/', 'name' => 'Saison 1'],
            ],
        ]);

        $this->assertDatabaseHas('animes', ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto', 'slug' => 'naruto']);
        $this->assertDatabaseHas('seasons', ['url' => 'https://anime-sama.fr/catalogue/naruto/saison1/']);

        $season = $anime->seasons()->first();
        $result = $store->syncEpisodes($season, [[
            'index' => 1,
            'name' => 'Episode 1',
            'languages' => [
                'VF' => [['https://vidmoly.net/embed-x.html', 'https://uqload.com/embed-y.html']],
                'VOSTFR' => [['https://vidmoly.net/embed-z.html']],
            ],
        ]]);

        $this->assertSame(1, $result['episodes']);
        $this->assertSame(3, $result['links']);
        $this->assertDatabaseHas('episodes', ['season_id' => $season->id, 'index' => 1]);
        $this->assertDatabaseHas('episode_links', [
            'url' => 'https://vidmoly.net/embed-x.html',
            'language' => 'VF',
            'host' => 'vidmoly.net',
        ]);
    }

    public function test_resyncing_replaces_links_without_duplication(): void
    {
        $store = app(LibraryStore::class);
        $anime = $store->upsertAnimeFromDetail('https://anime-sama.fr/catalogue/naruto/', [
            'name' => 'Naruto',
            'seasons' => [['url' => 'https://anime-sama.fr/catalogue/naruto/saison1/', 'name' => 'Saison 1']],
        ]);
        $season = $anime->seasons()->first();

        $store->syncEpisodes($season, [['index' => 1, 'languages' => ['VF' => [['https://a/1', 'https://b/1']]]]]);
        $this->assertSame(2, EpisodeLink::count());

        // Re-sync with a single mirror: old links are replaced, not duplicated.
        $store->syncEpisodes($season, [['index' => 1, 'languages' => ['VF' => [['https://a/1']]]]]);
        $this->assertSame(1, EpisodeLink::count());
    }

    public function test_ingest_full_populates_all_tables(): void
    {
        Http::fake([
            '*/api/all-catalogues*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto', 'alternative_names' => []],
            ], 200),
            '*/api/catalogue*' => Http::response([
                'name' => 'Naruto',
                'seasons' => [['url' => 'https://anime-sama.fr/catalogue/naruto/saison1/', 'name' => 'Saison 1']],
            ], 200),
            '*/api/episodes*' => Http::response([
                ['index' => 1, 'name' => 'Episode 1', 'languages' => ['VF' => [['https://vidmoly.net/embed-1.html']]], 'available_languages' => ['VF']],
            ], 200),
        ]);

        $this->artisan('library:ingest --full --sleep=0')->assertSuccessful();

        $this->assertDatabaseCount('animes', 1);
        $this->assertDatabaseCount('seasons', 1);
        $this->assertDatabaseCount('episodes', 1);
        $this->assertDatabaseCount('episode_links', 1);
        $this->assertDatabaseHas('episode_links', ['url' => 'https://vidmoly.net/embed-1.html', 'host' => 'vidmoly.net']);
    }
}
