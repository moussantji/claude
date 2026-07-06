<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnimeScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_parses_the_catalogue_html(): void
    {
        // anime-sama renders each field's text at end of line with the closing
        // tag on the next line, which is what the `>(.*)\n?<` patterns expect.
        $html = <<<'HTML'
        <div class="cards">
        <a href="https://anime-sama.fr/catalogue/naruto/">
        <img src="https://cdn.example/naruto.jpg" />
        <h2 class="t">Naruto
        </h2>
        <p class="alt">Naruto Shippuden
        </p>
        <p class="g">Action - Aventure
        </p>
        <p class="c">Anime
        </p>
        <p class="l">VOSTFR, VF
        </p>
        </a>
        </div>
        HTML;

        Http::fake([
            'https://anime-sama.fr/catalogue*' => Http::response($html, 200),
        ]);

        $this->getJson('/api/anime/search?q=naruto')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Naruto')
            ->assertJsonPath('data.0.categories', ['Anime'])
            ->assertJsonPath('data.0.languages', ['VF', 'VOSTFR'])
            ->assertJsonPath('data.0.genres', ['Action', 'Aventure'])
            ->assertJsonPath('data.0.image_url', 'https://cdn.example/naruto.jpg');
    }

    public function test_search_requires_a_query(): void
    {
        $this->getJson('/api/anime/search')->assertStatus(422);
    }

    public function test_upstream_error_is_translated(): void
    {
        Http::fake([
            'https://anime-sama.fr/catalogue*' => Http::response('boom', 500),
        ]);

        $this->getJson('/api/anime/search?q=x')
            ->assertStatus(502)
            ->assertJsonPath('source', 'anime-sama');
    }

    public function test_unreachable_site_returns_503(): void
    {
        Http::fake(function () {
            throw new ConnectionException('site down');
        });

        $this->getJson('/api/anime/search?q=x')->assertStatus(503);
    }

    public function test_episodes_are_parsed_from_language_pages(): void
    {
        $vostfrHtml = <<<'HTML'
        <html><head>
        <script src="episodes.js?filever=123"></script>
        </head><body>
        <script>
        function afficheListe(){
        resetListe();
        creerListe(1, 2);
        }
        </script>
        </body></html>
        HTML;

        $episodesJs = <<<'JS'
        var eps1 = ['https://a.example/1','https://a.example/2'];
        var eps2 = ['https://b.example/1','https://b.example/2'];
        JS;

        Http::fake([
            'https://anime-sama.fr/catalogue/naruto/saison1/vostfr/episodes.js*' => Http::response($episodesJs, 200),
            'https://anime-sama.fr/catalogue/naruto/saison1/vostfr/' => Http::response($vostfrHtml, 200),
            '*' => Http::response('', 404),
        ]);

        $response = $this->getJson('/api/anime/episodes?url='.urlencode('https://anime-sama.fr/catalogue/naruto/saison1/'))
            ->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.index', 1)
            ->assertJsonPath('data.0.name', 'Episode 1')
            ->assertJsonPath('data.0.available_languages', ['VOSTFR'])
            // Players swap the first two mirrors, so eps2's URL comes first.
            ->assertJsonPath('data.0.best_player', 'https://b.example/1')
            ->assertJsonPath('data.1.index', 2)
            ->assertJsonPath('data.1.best_player', 'https://b.example/2');
    }
}
