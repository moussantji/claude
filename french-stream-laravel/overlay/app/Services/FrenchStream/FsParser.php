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
     * Best-effort detail parsing. The synopsis / trailer / poster are usually
     * already known from the card; here we additionally try to collect the
     * embedded players (iframes) from the full story page.
     *
     * NOTE: the exact player markup of french-stream.one could not be verified
     * from a static homepage sample. Update the selectors below once a real
     * detail page (/index.php?newsid=ID) HTML is available.
     *
     * @return array{title: ?string, synopsis: ?string, poster: ?string, players: array<int, array{label: string, url: string}>}
     */
    public function detail(string $html): array
    {
        $crawler = $this->crawler($html);

        $players = [];
        $crawler->filter('iframe')->each(function (Crawler $frame) use (&$players) {
            $src = $frame->attr('src') ?? $frame->attr('data-src');
            if (! $src) {
                return;
            }
            // Ignore the YouTube trailer iframe and ad frames.
            if (str_contains($src, 'youtube') || str_contains($src, 'youtu.be')) {
                return;
            }
            $players[] = [
                'label' => 'Lecteur ' . (count($players) + 1),
                'url' => $this->client->absolute($src),
            ];
        });

        return [
            'title' => $this->firstText($crawler, 'h1'),
            'synopsis' => $this->firstText($crawler, '#s-desc') ?? $this->firstText($crawler, '.fdesc'),
            'poster' => $this->client->absolute($this->firstAttr($crawler, '.fposter img', 'src')),
            'players' => $players,
        ];
    }
}
