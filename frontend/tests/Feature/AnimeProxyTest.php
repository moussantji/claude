<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnimeProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_proxies_to_the_backend(): void
    {
        Http::fake([
            '*/api/search*' => Http::response([
                ['url' => 'https://anime-sama.fr/catalogue/naruto/', 'name' => 'Naruto'],
            ], 200),
        ]);

        $this->getJson('/api/anime/search?q=naruto')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto');
    }

    public function test_search_requires_a_query(): void
    {
        $this->getJson('/api/anime/search')->assertStatus(422);
    }

    public function test_episodes_proxy_preserves_language_data(): void
    {
        Http::fake([
            '*/api/episodes*' => Http::response([
                [
                    'index' => 1,
                    'name' => 'Episode 1',
                    'season_name' => 'Saison 1',
                    'serie_name' => 'Naruto',
                    'long_name' => 'Saison 1 - Episode 1',
                    'short_name' => 'Naruto S01E01',
                    'languages' => [
                        'VF' => [['https://mirror-a/1', 'https://mirror-b/1']],
                        'VOSTFR' => [['https://mirror-c/1']],
                    ],
                    'available_languages' => ['VF', 'VOSTFR'],
                    'best_player' => 'https://mirror-a/1',
                ],
            ], 200),
        ]);

        $this->getJson('/api/anime/episodes?url=https://anime-sama.fr/catalogue/naruto/saison1/vostfr/')
            ->assertOk()
            ->assertJsonPath('data.0.available_languages', ['VF', 'VOSTFR'])
            ->assertJsonPath('data.0.languages.VF.0.0', 'https://mirror-a/1');
    }

    public function test_trailer_proxies_youtube_key(): void
    {
        Http::fake([
            '*/api/trailer*' => Http::response(['youtube_key' => 'abc123'], 200),
        ]);

        $this->getJson('/api/anime/trailer?title=Naruto')
            ->assertOk()
            ->assertJsonPath('data.youtube_key', 'abc123');
    }

    public function test_backend_error_is_translated(): void
    {
        Http::fake([
            '*/api/search*' => Http::response(['detail' => 'boom'], 502),
        ]);

        $this->getJson('/api/anime/search?q=x')
            ->assertStatus(502)
            ->assertJsonPath('source', 'anime-sama-backend');
    }

    public function test_unreachable_backend_returns_503(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('down');
        });

        $this->getJson('/api/anime/search?q=x')->assertStatus(503);
    }
}
