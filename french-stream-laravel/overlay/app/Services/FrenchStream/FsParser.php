<?php

namespace App\Services\FrenchStream;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses french-stream.one HTML into plain arrays.
 *
 * Based on the real DataLife Engine markup (template VFR1):
 *   .short > .short-in
 *     .short-poster[href=/index.php?newsid=ID] > img[src]
 *     .film-quality (HD/CAM/...), .film-version (VF/VOSTFR/...)
 *     span[id^="trailer-"] (YouTube id), span[id^="desc-"] (synopsis)
 *     .mli-eps (episodes, series only), .short-title
 *   pagination: .pagi-nav .pnext a[href], .pagi-nav .navigation a[href]
 */
class FsParser
{
    public function __construct(protected FsClient $client) {}

    private function crawler(string $html): Crawler
    {
        $prev = libxml_use_internal_errors(true);
        $crawler = new Crawler($html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $crawler;
    }

    private function firstText(Crawler $node, string $selector): ?string
    {
        $f = $node->filter($selector);

        return $f->count() ? $this->clean($f->first()->text('')) : null;
    }

    private function firstAttr(Crawler $node, string $selector, string $attr): ?string
    {
        $f = $node->filter($selector);

        return $f->count() ? $f->first()->attr($attr) : null;
    }

    private function clean(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    /**
     * Parse every `.short` card found inside the given crawler/node.
     *
     * @return array<int, array>
     */
    public function cards(Crawler $scope): array
    {
        $items = [];

        $scope->filter('.short')->each(function (Crawler $short) use (&$items) {
            $posterLink = $short->filter('.short-poster');
            if ($posterLink->count() === 0) {
                return;
            }
            $href = (string) $posterLink->first()->attr('href');

            // Extract numeric id (?newsid=ID or /ID-slug.html)
            $id = null;
            if (preg_match('/newsid=(\d+)/', $href, $m)) {
                $id = $m[1];
            } elseif (preg_match('/(\d{3,})/', $href, $m)) {
                $id = $m[1];
            }
            if (! $id) {
                return;
            }

            $title = $this->firstText($short, '.short-title')
                ?: $this->clean($posterLink->first()->attr('alt'));

            $img = $this->firstAttr($short, '.short-poster img', 'src')
                ?? $this->firstAttr($short, 'img', 'src');

            $episodes = $this->firstText($short, '.mli-eps');

            $items[] = [
                'id' => $id,
                'title' => $title,
                'poster' => $this->client->absolute($img),
                'quality' => $this->firstText($short, '.film-quality'),
                'version' => $this->firstText($short, '.film-version'),
                'episodes' => $episodes,
                'is_series' => $episodes !== null,
                'trailer' => $this->firstText($short, 'span[id^="trailer-"]'),
                'synopsis' => $this->firstText($short, 'span[id^="desc-"]'),
                'url' => $this->client->absolute('/index.php?newsid=' . $id),
            ];
        });

        return $items;
    }

    /**
     * Parse the homepage into titled sections, each with its cards.
     *
     * @return array<int, array{title: string, link: ?string, items: array}>
     */
    public function homeSections(string $html): array
    {
        $crawler = $this->crawler($html);
        $sections = [];

        $crawler->filter('.pages .sect')->each(function (Crawler $sect) use (&$sections) {
            $title = $this->firstText($sect, '.st-capt');
            if (! $title) {
                return;
            }
            $items = $this->cards($sect);
            if (empty($items)) {
                return;
            }
            $sections[] = [
                'title' => $title,
                'link' => $this->client->absolute($this->firstAttr($sect, '.st-capt', 'href')),
                'items' => $items,
            ];
        });

        return $sections;
    }

    /**
     * Parse a listing/search page: cards + next-page URL.
     *
     * @return array{items: array, next: ?string}
     */
    public function listing(string $html): array
    {
        $crawler = $this->crawler($html);

        $next = $this->firstAttr($crawler, '.pagi-nav .pnext a', 'href');

        return [
            'items' => $this->cards($crawler),
            'next' => $this->client->absolute($next),
        ];
    }

    /**
     * Detail page parsing (DataLife Engine full story page).
     *
     * IMPORTANT: the streaming players are NOT present in the HTML. french-stream
     * injects them client-side via /js/serie-player12.js using a token from
     * /engine/ajax/vidzy_token.php (the #seriePlayer iframe ships with an empty
     * src and #players-list / #vf-episodes are empty). A server-side HTML scraper
     * therefore cannot extract stream links. We parse the rich metadata that IS
     * in the HTML (title, synopsis, genres, cast, poster, YouTube trailer) and
     * flag whether the JS/token player is in use so the UI can react honestly.
     *
     * @return array{title: ?string, synopsis: ?string, poster: ?string, trailer: ?string, facts: array, status: ?string, episodes: ?string, meta: array<string,string>, cast: array<int,string>, players: array<int, array{label:string,url:string}>, js_player: bool}
     */
    public function detail(string $html): array
    {
        $crawler = $this->crawler($html);

        // #serie-data / #film-data carries clean metadata as data-attributes.
        $data = $crawler->filter('#serie-data, #film-data');
        $dataAttr = fn (string $a) => $data->count() ? $data->first()->attr($a) : null;

        $title = $this->firstText($crawler, 'h1#s-title')
            ?: $this->firstText($crawler, 'h1')
            ?: $dataAttr('data-title');

        $trailer = $dataAttr('data-trailer');

        $poster = $this->client->absolute(
            $dataAttr('data-affiche') ?: $this->firstAttr($crawler, '.fposter img', 'src')
        );

        $synopsis = $this->firstText($crawler, '.fdesc') ?? $this->firstText($crawler, '#s-desc');

        $facts = [
            'certification' => $this->firstText($crawler, '.facts .certification'),
            'year' => trim((string) $this->firstText($crawler, '.facts .release'), " -\t"),
            'genres' => $this->firstText($crawler, '.facts .genres'),
            'runtime' => $this->firstText($crawler, '.facts .runtime'),
        ];

        $status = $this->firstText($crawler, '.short-qual');
        $episodes = $this->firstText($crawler, '.short-label');

        // Key/value metadata list (Titre original, Réalisateur, Avec, ...)
        $meta = [];
        $crawler->filter('#s-list li')->each(function (Crawler $li) use (&$meta) {
            $key = $this->firstText($li, 'span');
            if (! $key) {
                return;
            }
            $value = $this->clean(preg_replace('/^\s*' . preg_quote($key, '/') . '/u', '', $this->clean($li->text(''))));
            $meta[rtrim($key, ' :')] = $value;
        });

        // Cast with photos is exposed via a JS array: window.actorData = [...]
        $cast = [];
        if (preg_match('/window\.actorData\s*=\s*(\[[^\]]*\])/s', $html, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    // "Name (Role) - https://.../photo.jpg"
                    $cast[] = trim(explode(' - http', (string) $entry)[0]);
                }
            }
        }

        // Streams are JS/token-injected; the iframe ships empty. Detect that.
        $jsPlayer = $crawler->filter('#seriePlayer, #serie-config, #cn-content, .movie-players')->count() > 0;

        // Very rare: a static (non-empty) player iframe.
        $players = [];
        $crawler->filter('.iframe iframe, .movie-players iframe')->each(function (Crawler $f) use (&$players) {
            $src = $f->attr('src') ?: $f->attr('data-src');
            if ($src && ! str_contains($src, 'youtube')) {
                $players[] = ['label' => 'Lecteur ' . (count($players) + 1), 'url' => $this->client->absolute($src)];
            }
        });

        return [
            'title' => $title,
            'synopsis' => $synopsis,
            'poster' => $poster,
            'trailer' => $trailer,
            'facts' => $facts,
            'status' => $status,
            'episodes' => $episodes,
            'meta' => $meta,
            'cast' => $cast,
            'players' => $players,
            'js_player' => $jsPlayer,
        ];
    }
}
