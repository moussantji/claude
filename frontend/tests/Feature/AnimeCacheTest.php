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
