<?php

namespace App\Services\FzMovies;

use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses raw fzmovies HTML into plain arrays.
 *
 * This is a faithful PHP port of the reference SDK's `handlers.py`
 * (search_handler, movie_handler, to_download_handler, download_links_handler).
 */
class Parser
{
    public function __construct(protected FzMoviesClient $client) {}

    private function crawler(string $html): Crawler
    {
        // DomCrawler emits libxml warnings on the site's custom tags; silence them.
        $previous = libxml_use_internal_errors(true);
        $crawler = new Crawler($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $crawler;
    }

    private function text(Crawler $node, string $selector): ?string
    {
        $found = $node->filter($selector);

        return $found->count() ? trim($found->first()->text('')) : null;
    }

    private function attr(Crawler $node, string $selector, string $attr): ?string
    {
        $found = $node->filter($selector);

        return $found->count() ? $found->first()->attr($attr) : null;
    }

    /**
     * Parse a search / listing page into a SearchResults-like array.
     *
     * @return array{movies: array<int, array>, first_page: ?string, previous_page: ?string, next_page: ?string, last_page: ?string}
     */
    public function searchResults(string $html): array
    {
        $crawler = $this->crawler($html);
        $movies = [];

        $crawler->filter('div.mainbox')->each(function (Crawler $node) use (&$movies) {
            if ($node->filter('a')->count() === 0) {
                return;
            }
            $url = $node->filter('a')->first()->attr('href');

            $span = $node->filter('span');
            if ($span->count() === 0) {
                return; // no metadata block -> skip
            }

            $smalls = $span->filter('small');
            $count = $smalls->count();
            if ($count < 3) {
                return;
            }

            if ($count >= 4) {
                $title = trim($smalls->eq(0)->text(''));
                $year = trim($smalls->eq(1)->text(''));
                $distribution = trim($smalls->eq(2)->text(''));
                $about = trim($smalls->eq(3)->text(''));
            } else {
                $title = trim($smalls->eq(0)->text(''));
                $year = trim($smalls->eq(1)->text(''));
                $distribution = 'Unknown';
                $about = trim($smalls->eq(2)->text(''));
            }

            $cover = $this->attr($node, 'img', 'src');

            $movies[] = [
                'url' => $this->client->absolute($url),
                'title' => $title,
                'year' => preg_replace('/[()]/', '', $year),
                'distribution' => preg_replace('/[()]/', '', $distribution),
                'about' => $about,
                'cover_photo' => $this->client->absolute($cover),
            ];
        });

        // Pagination links live inside div.mainbox2
        $pages = ['first_page' => null, 'previous_page' => null, 'next_page' => null, 'last_page' => null];
        $nav = $crawler->filter('div.mainbox2');
        if ($nav->count()) {
            $nav->first()->filter('a')->each(function (Crawler $a) use (&$pages) {
                $label = trim($a->text(''));
                $href = $this->client->absolute($a->attr('href'));
                match ($label) {
                    'First' => $pages['first_page'] = $href,
                    'Prev' => $pages['previous_page'] = $href,
                    'Next' => $pages['next_page'] = $href,
                    'Last' => $pages['last_page'] = $href,
                    default => null,
                };
            });
        }

        return array_merge(['movies' => $movies], $pages);
    }

    /**
     * Parse a movie page into a MovieFiles-like array.
     *
     * @return array{files: array<int, array>, trailer: ?string, recommended: array<int, array>}
     */
    public function movieFiles(string $html): array
    {
        $crawler = $this->crawler($html);

        // Trailer (embedded iframe)
        $trailer = null;
        $iframes = $crawler->filter('iframe');
        $iframes->each(function (Crawler $frame) use (&$trailer) {
            if ($trailer !== null) {
                return;
            }
            $allow = (string) $frame->attr('allow');
            if (str_contains($allow, 'encrypted-media') || $frame->attr('src')) {
                $trailer = $frame->attr('src');
            }
        });

        // Recommended movies (carousel)
        $recommended = [];
        $carousel = $crawler->filter('div.owl-carousel.owl-theme');
        if ($carousel->count()) {
            $carousel->first()->filter('a')->each(function (Crawler $a) use (&$recommended) {
                $recommended[] = [
                    'title' => $a->attr('alt'),
                    'url' => $this->client->absolute($a->attr('href')),
                    'cover_photo' => $a->filter('img')->count()
                        ? $this->client->absolute($a->filter('img')->first()->attr('src'))
                        : null,
                ];
            });
        }

        // Downloadable files
        $files = [];
        $crawler->filter('ul.moviesfiles')->each(function (Crawler $ul) use (&$files) {
            $links = $ul->filter('a');
            if ($links->count() === 0) {
                return;
            }

            $title = trim($links->eq(0)->text(''));
            $url = $links->eq(0)->attr('href');

            $size = null;
            $hits = null;
            $dcounter = $ul->filter('dcounter');
            if ($dcounter->count()) {
                $raw = preg_replace('/[(){}]/', '', trim($dcounter->first()->text('')));
                $parts = preg_split('/\s+/', trim($raw)) ?: [];
                $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
                $size = implode(' ', array_slice($parts, 0, 2));
                $hits = count($parts) >= 3 ? $parts[count($parts) - 3] : null;
            }

            $mediainfo = $links->count() >= 2 ? $links->eq(1)->attr('href') : null;
            $ss = $links->count() >= 3 ? $links->eq(2)->attr('href') : null;

            $files[] = [
                'title' => $title,
                'url' => $this->client->absolute($url),
                'size' => $size,
                'hits' => $hits,
                'mediainfo' => $this->client->absolute($mediainfo),
                'ss' => $this->client->absolute($ss),
            ];
        });

        return [
            'files' => $files,
            'trailer' => $trailer,
            'recommended' => $recommended,
        ];
    }

    /**
     * From a "to-download" page, extract the URL of the download-links page.
     */
    public function toDownloadLink(string $html): string
    {
        $crawler = $this->crawler($html);
        $link = $this->attr($crawler, 'a#downloadlink', 'href');
        if (! $link) {
            throw new RuntimeException("Lien de téléchargement introuvable sur la page.");
        }

        return $this->client->absolute($link);
    }

    /**
     * From a "dlink" page (/dlink.php?id=...), extract the final direct URL
     * of the movie file (used by the embedded player).
     */
    public function finalDownloadLink(string $html): string
    {
        $crawler = $this->crawler($html);
        $link = $this->attr($crawler, 'div.mainbox3 a', 'href');
        if (! $link) {
            throw new RuntimeException("URL directe du fichier introuvable.");
        }

        return $this->client->absolute($link);
    }

    /**
     * Parse the download-links page into a DownloadMovie-like array.
     *
     * @return array{filename: ?string, size: ?string, info: ?string, links: array<int, array>}
     */
    public function downloadLinks(string $html): array
    {
        $crawler = $this->crawler($html);

        $info = $this->text($crawler, 'div.mainbox4');

        $desc = $crawler->filter('div.moviedesc');
        $filename = null;
        $size = null;
        if ($desc->count()) {
            $filename = $this->text($desc->first(), 'textcolor1');
            $size = $this->text($desc->first(), 'textcolor2');
        }

        // Take the last of the first three "downloadlinks" lists (matches SDK).
        $lists = $crawler->filter('ul.downloadlinks');
        $links = [];
        if ($lists->count()) {
            $take = min($lists->count(), 3);
            $target = $lists->eq($take - 1);
            $target->filter('li')->each(function (Crawler $li) use (&$links) {
                if ($li->filter('a')->count() === 0) {
                    return;
                }
                $url = $li->filter('a')->first()->attr('href');
                $connections = null;
                if ($li->filter('dcounter')->count()) {
                    $raw = preg_replace('/[()]/', '', trim($li->filter('dcounter')->first()->text('')));
                    $parts = preg_split('/\s+/', trim($raw)) ?: [];
                    $connections = $parts[0] ?? null;
                }
                $links[] = [
                    'url' => $this->client->absolute($url),
                    'connections' => $connections,
                ];
            });
        }

        return [
            'filename' => $filename,
            'size' => $size,
            'info' => $info,
            'links' => $links,
        ];
    }
}
