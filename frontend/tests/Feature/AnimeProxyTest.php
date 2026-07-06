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
