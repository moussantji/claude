<?php

namespace Tests\Feature;

use App\Models\AnimeCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnimeCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Persistence is disabled by default in the test env; turn it on with a
        // real freshness window for these tests.
        config()->set('services.anime_sama.store', true);
        config()->set('services.anime_sama.ttl.search', 3600);
    }

    public function test_response_is_persisted_and_reused_from_the_database(): void
    {
        Http::fake([
            '*/api/search*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto'],
            ], 200),
        ]);

        $this->getJson('/api/anime/search?q=naruto')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto');

        // The payload (incl. any player links) is now stored in the DB.
        $this->assertDatabaseCount('anime_caches', 1);
        $this->assertDatabaseHas('anime_caches', ['type' => 'search']);

        // A second identical request is served from the DB — no upstream call.
        $this->getJson('/api/anime/search?q=naruto')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_stale_data_is_served_when_the_backend_fails(): void
    {
        Http::fake([
            '*/api/search*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto'],
            ], 200),
        ]);

        // Prime the cache.
        $this->getJson('/api/anime/search?q=naruto')->assertOk();

        // Make the stored row stale.
        AnimeCache::query()->update(['fetched_at' => now()->subDay()]);

        // Upstream now fails — we should still get the stale payload, not a 502.
        Http::fake([
            '*/api/search*' => Http::response(['detail' => 'anime-sama down'], 502),
        ]);

        $this->getJson('/api/anime/search?q=naruto')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto');
    }

    public function test_missing_data_with_failing_backend_still_errors(): void
    {
        // No cached row + failing upstream => the error surfaces (nothing to serve).
        Http::fake([
            '*/api/search*' => Http::response(['detail' => 'boom'], 502),
        ]);

        $this->getJson('/api/anime/search?q=bleach')->assertStatus(502);
        $this->assertDatabaseCount('anime_caches', 0);
    }

    public function test_ingest_stores_the_catalogue_and_search_is_served_locally(): void
    {
        Http::fake([
            '*/api/new-episodes*' => Http::response([], 200),
            '*/api/planning*' => Http::response(['detail' => 'not implemented'], 501),
            '*/api/all-catalogues*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto', 'alternative_names' => []],
                ['url' => 'https://anime-sama.fr/catalogue/bleach/', 'name' => 'Bleach', 'alternative_names' => ['Burichi']],
            ], 200),
        ]);

        $this->artisan('anime:ingest')->assertSuccessful();

        $this->assertDatabaseHas('anime_caches', ['cache_key' => 'all-catalogues', 'type' => 'catalogues']);

        // From here, search must be served from the local snapshot with no
        // upstream request at all.
        Http::fake();

        $this->getJson('/api/anime/search?q=naruto')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Naruto');

        // Alternative-name match works too.
        $this->getJson('/api/anime/search?q=burichi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bleach');

        Http::assertNothingSent();
    }

    public function test_local_search_matches_despite_spacing_and_case(): void
    {
        config()->set('services.anime_sama.local_search', true);

        AnimeCache::create([
            'key_hash' => hash('sha256', 'all-catalogues'),
            'cache_key' => 'all-catalogues',
            'type' => 'catalogues',
            'payload' => [
                ['url' => 'https://anime-sama.fr/catalogue/konosuba/', 'name' => 'Kono Subarashii Sekai ni Shukufuku wo!', 'alternative_names' => []],
            ],
            'fetched_at' => now(),
        ]);

        Http::fake(); // any upstream call would be recorded

        $this->getJson('/api/anime/search?q=konosuba')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Http::assertNothingSent();
    }

    public function test_search_falls_back_to_backend_when_snapshot_misses(): void
    {
        config()->set('services.anime_sama.local_search', true);

        // Snapshot exists but does NOT contain the title (all_catalogues is not
        // exhaustive), so search must fall back to the site's own search.
        AnimeCache::create([
            'key_hash' => hash('sha256', 'all-catalogues'),
            'cache_key' => 'all-catalogues',
            'type' => 'catalogues',
            'payload' => [
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto', 'alternative_names' => []],
            ],
            'fetched_at' => now(),
        ]);

        Http::fake([
            '*/api/search*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/konosuba/', 'name' => 'KonoSuba'],
            ], 200),
        ]);

        $this->getJson('/api/anime/search?q=konosuba')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'KonoSuba');

        Http::assertSentCount(1);
    }

    public function test_full_ingest_stores_episode_player_links(): void
    {
        Http::fake([
            '*/api/all-catalogues*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto', 'alternative_names' => []],
            ], 200),
            '*/api/new-episodes*' => Http::response([], 200),
            '*/api/planning*' => Http::response(['detail' => 'not implemented'], 501),
            '*/api/catalogue*' => Http::response([
                'name' => 'Naruto',
                'seasons' => [
                    ['url' => 'https://anime-sama.fr/catalogue/naruto/saison1/', 'name' => 'Saison 1', 'serie_name' => 'Naruto'],
                ],
            ], 200),
            '*/api/episodes*' => Http::response([
                [
                    'index' => 1,
                    'name' => 'Episode 1',
                    'season_name' => 'Saison 1',
                    'serie_name' => 'Naruto',
                    'long_name' => 'Saison 1 - Episode 1',
                    'short_name' => 'Naruto S01E01',
                    'languages' => ['VF' => [['https://mirror-a/ep1', 'https://mirror-b/ep1']]],
                    'available_languages' => ['VF'],
                    'best_player' => 'https://mirror-a/ep1',
                ],
            ], 200),
        ]);

        $this->artisan('anime:ingest --full --sleep=0')->assertSuccessful();

        // The episode set (with its player/vid links) is now stored.
        $this->assertDatabaseHas('anime_caches', [
            'type' => 'episodes',
            'cache_key' => 'episodes:https://anime-sama.fr/catalogue/naruto/saison1/:',
        ]);

        $row = AnimeCache::query()->where('type', 'episodes')->first();
        $this->assertSame('https://mirror-a/ep1', $row->payload[0]['languages']['VF'][0][0]);
    }

    public function test_links_command_lists_stored_video_links(): void
    {
        AnimeCache::create([
            'key_hash' => hash('sha256', 'episodes:https://anime-sama.fr/catalogue/naruto/saison1/:'),
            'cache_key' => 'episodes:https://anime-sama.fr/catalogue/naruto/saison1/:',
            'type' => 'episodes',
            'payload' => [[
                'index' => 1,
                'serie_name' => 'naruto',
                'season_name' => 'saison1',
                'languages' => [
                    'VF' => [['https://host-a/vf1', 'https://host-b/vf1']],
                    'VOSTFR' => [['https://host-a/vostfr1']],
                ],
                'available_languages' => ['VF', 'VOSTFR'],
            ]],
            'fetched_at' => now(),
        ]);

        $this->artisan('anime:links --lang=VF')
            ->expectsOutputToContain('https://host-a/vf1')
            ->assertSuccessful();

        // JSON export works too.
        $this->artisan('anime:links --json')->assertSuccessful();
    }

    public function test_cache_status_reports_stored_data(): void
    {
        AnimeCache::create([
            'key_hash' => hash('sha256', 'all-catalogues'),
            'cache_key' => 'all-catalogues',
            'type' => 'catalogues',
            'payload' => [['name' => 'Naruto', 'url' => 'https://anime-sama.fr/catalogue/naruto/']],
            'fetched_at' => now(),
        ]);

        $this->artisan('anime:cache-status --key=all-catalogues')
            ->expectsOutputToContain('Naruto')
            ->assertSuccessful();

        $this->artisan('anime:cache-status')->assertSuccessful();
    }

    public function test_prune_removes_old_rows(): void
    {
        AnimeCache::create([
            'key_hash' => hash('sha256', 'old'),
            'cache_key' => 'old',
            'type' => 'search',
            'payload' => [],
            'fetched_at' => now()->subDays(40),
        ]);
        AnimeCache::create([
            'key_hash' => hash('sha256', 'fresh'),
            'cache_key' => 'fresh',
            'type' => 'search',
            'payload' => [],
            'fetched_at' => now(),
        ]);

        $this->artisan('anime:cache-prune', ['--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseCount('anime_caches', 1);
        $this->assertDatabaseHas('anime_caches', ['cache_key' => 'fresh']);
    }
}
