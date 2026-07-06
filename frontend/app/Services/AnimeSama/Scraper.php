<?php

namespace App\Services\AnimeSama;

use App\Exceptions\AnimeSamaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

/**
 * Native PHP port of the Python `anime-sama_api` library.
 *
 * Scrapes the public anime-sama website and returns plain arrays matching the
 * JSON shapes consumed by the controllers. This replaces the former Python
 * FastAPI backend so the whole project runs as a single Laravel application.
 */
class Scraper
{
    protected string $siteUrl;

    protected string $providerUrl;

    /** @var list<string> */
    protected array $preferLanguages;

    protected string $userAgent;

    protected float $timeout;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected Factory $http, array $config = [])
    {
        $this->siteUrl = $this->ensureSlash($config['site_url'] ?? 'https://anime-sama.fr/');
        $this->providerUrl = $this->ensureSlash($config['provider_url'] ?? 'https://anime-sama.pw/');
        $this->userAgent = $config['user_agent'] ?? 'Mozilla/5.0 (compatible; anime-sama-laravel/1.0)';
        $this->timeout = (float) ($config['timeout'] ?? 20.0);
        $this->preferLanguages = $this->splitList($config['prefer_languages'] ?? 'VF,VOSTFR');

        // Big HTML pages need generous PCRE limits for the `[\W\w]+?` patterns.
        @ini_set('pcre.backtrack_limit', '20000000');
        @ini_set('pcre.recursion_limit', '2000000');
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    /**
     * @return array{site_url: string}
     */
    public function site(): array
    {
        return ['site_url' => $this->siteUrl];
    }

    /**
     * Auto-detect the current anime-sama domain via the provider.
     *
     * @return array{site_url: string}
     */
    public function resolveSite(): array
    {
        $response = $this->getOrNull($this->providerUrl);

        if ($response === null || $response->failed()) {
            throw new AnimeSamaException('Could not reach the anime-sama domain provider.', 502);
        }

        if (preg_match('#href="(.+?)">Accéder à Anime-Sama#i', $response->body(), $match) !== 1) {
            throw new AnimeSamaException('Could not resolve the anime-sama domain.', 502);
        }

        try {
            $redirected = $this->request()->get($match[1]);
        } catch (ConnectionException $e) {
            throw new AnimeSamaException('Could not follow the provider redirect.', 502, $e);
        }

        $this->siteUrl = $this->ensureSlash((string) $redirected->effectiveUri());

        return ['site_url' => $this->siteUrl];
    }

    // ---------------------------------------------------------------------
    // Catalogue search
    // ---------------------------------------------------------------------

    /**
     * Search catalogues by query (empty query returns everything).
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $searchUrl = $this->siteUrl.'catalogue/?search='.rawurlencode($query);
        $first = $this->get($searchUrl);

        if ($first->failed()) {
            throw new AnimeSamaException('anime-sama returned status '.$first->status().'.', 502);
        }

        preg_match_all('#page=(\d+)#', $first->body(), $pageMatches);
        $lastPage = empty($pageMatches[1]) ? 1 : (int) end($pageMatches[1]);

        $bodies = [$first->body()];

        if ($lastPage > 1) {
            $responses = $this->http->pool(function (Pool $pool) use ($query, $lastPage): array {
                $requests = [];
                for ($page = 2; $page <= $lastPage; $page++) {
                    $url = $this->siteUrl.'catalogue/?search='.rawurlencode($query).'&page='.$page;
                    $requests[] = $this->poolRequest($pool, (string) $page)->get($url);
                }

                return $requests;
            });

            foreach ($responses as $response) {
                if ($response instanceof Response && $response->successful()) {
                    $bodies[] = $response->body();
                }
            }
        }

        $catalogues = [];
        foreach ($bodies as $body) {
            foreach ($this->parseCatalogues($body) as $catalogue) {
                $catalogues[] = $catalogue;
            }
        }

        return $catalogues;
    }

    /**
     * Latest episode releases from the homepage (oldest -> newest).
     *
     * @return list<array<string, mixed>>
     */
    public function newEpisodes(): array
    {
        $section = $this->homepageSection('ajouts animes', 4);
        $releases = $this->parseReleaseEpisodes($section);

        return array_reverse($releases);
    }

    /**
     * Full detail of a catalogue.
     *
     * @return array<string, mixed>
     */
    public function catalogue(string $url): array
    {
        $url = $this->ensureSlash($url);
        $response = $this->getOrNull($url);
        $page = $response instanceof Response && $response->successful() ? $response->body() : '';

        $name = $this->lastSegment($url);

        return [
            'url' => $url,
            'name' => $name,
            'alternative_names' => [],
            'genres' => [],
            'categories' => [],
            'languages' => [],
            'image_url' => '',
            'synopsis' => $this->firstMatch('#Synopsis[\W\w]+?>(.+)<#', $page),
            'advancement' => $this->firstMatch('#Actualité.+?>(.+?)<#', $page),
            'correspondence' => $this->firstMatch('#Correspondance.+?>(.+?)<#', $page),
            'is_mature' => preg_match('#<div class=".*?yellow.*?">[\W\w]+?public averti#', $page) === 1,
            'seasons' => $this->parseSeasons($page, $url, $name),
        ];
    }

    /**
     * Episodes (with player links) for a season URL.
     *
     * @param  list<string>|null  $preferLanguages
     * @return list<array<string, mixed>>
     */
    public function episodes(string $seasonUrl, ?array $preferLanguages = null): array
    {
        $seasonUrl = $this->ensureSlash($seasonUrl);
        $prefer = $preferLanguages ?: $this->preferLanguages;

        $serieName = $this->nthSegmentFromEnd($seasonUrl, 2);
        $seasonName = $this->lastSegment($seasonUrl);

        $pages = $this->getAllPages($seasonUrl);
        if ($pages === []) {
            return [];
        }

        $playersPerPage = [];
        foreach ($pages as $page) {
            $playersPerPage[] = $this->parsePlayers($page['episodes_js']);
        }

        $maxEpisodes = 0;
        foreach ($playersPerPage as $players) {
            $maxEpisodes = max($maxEpisodes, count($players));
        }

        $namesPerPage = [];
        foreach ($pages as $i => $page) {
            $namesPerPage[] = $this->parseEpisodeNames(
                $page['html'],
                count($playersPerPage[$i]),
                $maxEpisodes,
            );
        }

        // Merge every language page into a single ordered episode list.
        $merged = [];
        foreach ($pages as $i => $page) {
            $merged = $this->mergeEpisodes($merged, $page['lang_id'], $namesPerPage[$i], $playersPerPage[$i]);
        }

        $episodes = [];
        foreach ($merged as $index => $entry) {
            $episode = new Episode(
                $entry['languages'],
                $serieName,
                $seasonName,
                $entry['name'],
                $index + 1,
            );
            $episodes[] = $episode->toArray($prefer);
        }

        return $episodes;
    }

    // ---------------------------------------------------------------------
    // HTML parsing helpers
    // ---------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseCatalogues(string $html): array
    {
        $html = preg_replace('#<script[\W\w]+?</script>#', '', $html) ?? $html;

        $pattern = '#href="('.$this->siteUrl.'catalogue/.+)"[\W\w]+?src="(.+?)"[\W\w]+?<h2.+?>(.*)\n?<[\W\w]+?<p.+?>(.*)\n?<[\W\w]+?<p.+?>(.*)\n?<[\W\w]+?<p.+?>(.*)\n?<[\W\w]+?<p.+?>(.*)\n?<#';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $catalogues = [];
        foreach ($matches as $match) {
            [$url, $imageUrl, $name, $altStr, $genresStr, $categoriesStr, $languagesStr] =
                array_map(fn (string $value): string => $this->unescape($value), array_slice($match, 1, 7));

            $alternativeNames = $altStr !== '' ? explode(', ', $altStr) : [];
            $genres = str_contains($genresStr, ' - ')
                ? explode(' - ', $genresStr)
                : ($genresStr !== '' ? explode(', ', $genresStr) : []);

            $categories = $this->filterAllowed($categoriesStr !== '' ? explode(', ', $categoriesStr) : [], Lang::CATEGORIES);
            $languages = $this->filterAllowed($languagesStr !== '' ? explode(', ', $languagesStr) : [], Lang::LANGS);

            sort($categories);
            sort($languages);

            $catalogues[] = [
                'url' => $url,
                'name' => $name !== '' ? $name : $this->lastSegment($url),
                'alternative_names' => $alternativeNames,
                'genres' => $genres,
                'categories' => $categories,
                'languages' => $languages,
                'image_url' => $imageUrl,
            ];
        }

        return $catalogues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseReleaseEpisodes(string $html): array
    {
        $pattern = '#href="('.$this->siteUrl.'catalogue/.+)"[\W\w]+?src="(.+?)"[\W\w]+?>(.*)\n?<[\W\w]+?>(.*)\n?<[\W\w]+?>(.*)\n?<[\W\w]+?>(.*)\n?<#';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $releases = [];
        foreach ($matches as $match) {
            [$seasonUrl, $imageUrl, $serieName, $categoriesStr, $language, $descriptive] = array_slice($match, 1, 6);

            $categories = $categoriesStr !== '' ? explode(', ', $categoriesStr) : ['Anime'];
            $categories = $this->filterAllowed($categories, Lang::CATEGORIES);
            $language = trim($language) !== '' ? trim($language) : 'VOSTFR';

            $releases[] = [
                'url' => $seasonUrl,
                'name' => $serieName,
                'categories' => $categories,
                'languages' => [$language],
                'image_url' => $imageUrl,
                'alternative_names' => [],
                'genres' => [],
                'descriptive' => $descriptive,
                'fancy_name' => trim("{$serieName} - {$descriptive}"),
            ];
        }

        return $releases;
    }

    /**
     * @return list<array{url: string, name: string, serie_name: string}>
     */
    protected function parseSeasons(string $page, string $catalogueUrl, string $serieName): array
    {
        $clean = $this->removeSomeJsComments($page);

        if (preg_match_all('#panneauAnime\("(.+?)", *"(.+?)(?:vostfr|vf)"\);#', $clean, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $seasons = [];
        foreach ($matches as $match) {
            $name = $match[1];
            $link = $match[2];
            $seasons[] = [
                'url' => $catalogueUrl.$link,
                'name' => $name,
                'serie_name' => $serieName,
            ];
        }

        return $seasons;
    }

    // ---------------------------------------------------------------------
    // Season page + episode parsing (ported from season.py)
    // ---------------------------------------------------------------------

    /**
     * Fetch every language page (and its episodes.js) for a season.
     *
     * @return list<array{lang_id: string, html: string, episodes_js: string}>
     */
    protected function getAllPages(string $seasonUrl): array
    {
        $urls = [];
        foreach (Lang::LANG_IDS as $langId) {
            $urls[$langId] = $seasonUrl.$langId.'/';
        }

        $responses = $this->http->pool(function (Pool $pool) use ($urls): array {
            $requests = [];
            foreach ($urls as $langId => $url) {
                $requests[] = $this->poolRequest($pool, $langId)->get($url);
            }

            return $requests;
        });

        $pagesDict = [];
        foreach (Lang::LANG_IDS as $langId) {
            $page = ['lang_id' => $langId, 'html' => '', 'episodes_js' => ''];
            $response = $responses[$langId] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $html = $response->body();
                if (preg_match('#episodes\.js\?filever=\d+#', $html, $jsMatch) === 1) {
                    $js = $this->getOrNull($urls[$langId].$jsMatch[0]);
                    if ($js instanceof Response && $js->successful()) {
                        $page['html'] = $html;
                        $page['episodes_js'] = $js->body();
                    }
                }
            }

            $pagesDict[$langId] = $page;
        }

        // The "VO" tab may actually be a specific language: duplicate it there.
        if ($pagesDict['vostfr']['html'] !== '') {
            $clean = $this->removeSomeJsComments($pagesDict['vostfr']['html']);
            if (preg_match('#src=".+flag_(.+?)\.png".*?[\n\t]*<p.*?>VO</p>#', $clean, $flagMatch) === 1) {
                $lang = Lang::FLAGID_TO_LANG[$flagMatch[1]] ?? null;
                if ($lang !== null) {
                    foreach (Lang::LANG_TO_IDS[$lang] as $langId) {
                        if (($pagesDict[$langId]['html'] ?? '') === '') {
                            $copy = $pagesDict['vostfr'];
                            $copy['lang_id'] = $langId;
                            $pagesDict[$langId] = $copy;
                            break;
                        }
                    }
                }
            }
        }

        $pages = [];
        foreach (Lang::LANG_IDS as $langId) {
            if ($pagesDict[$langId]['html'] !== '') {
                $pages[] = $pagesDict[$langId];
            }
        }

        return $pages;
    }

    /**
     * Extract the players (one {@see Players} per episode) from an episodes.js.
     *
     * @return list<Players>
     */
    protected function parsePlayers(string $episodesJs): array
    {
        $js = $this->removeSomeJsComments($episodesJs);

        if (preg_match_all('#eps(\d+) ?= ?\[([\W\w]+?)\]#', $js, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        // Sort player sources by their (string) number, like the Python code.
        usort($matches, static fn (array $a, array $b): int => strcmp($a[1], $b[1]));

        $sources = [];
        foreach ($matches as $match) {
            preg_match_all("#'(.+?)'#", $match[2], $urlMatches);
            $sources[] = $urlMatches[1];
        }

        // Transpose (zip_varlen): group the i-th URL of every source per episode.
        $maxLen = 0;
        foreach ($sources as $urls) {
            $maxLen = max($maxLen, count($urls));
        }

        $players = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $episodeUrls = [];
            foreach ($sources as $urls) {
                if (isset($urls[$i])) {
                    $episodeUrls[] = $urls[$i];
                }
            }
            $players[] = new Players($episodeUrls);
        }

        return $players;
    }

    /**
     * Reconstruct episode names from the page's JS list-building functions.
     *
     * @return list<string>
     */
    protected function parseEpisodeNames(string $html, int $numberOfEpisodes, int $maxEpisodes): array
    {
        $clean = $this->removeSomeJsComments($html);

        if (preg_match_all('#resetListe\(\); *[\n\r]+\t*(.*?)}#s', $clean, $matches) === false || empty($matches[1])) {
            return [];
        }

        $functions = end($matches[1]);
        $parts = $this->splitAndStrip($functions, [';', "\n"]);
        array_pop($parts); // Python drops the trailing element.

        $names = [];
        foreach ($parts as $part) {
            if ($part === '' || str_starts_with($part, '//')) {
                continue;
            }

            $callStart = strpos($part, '(');
            if ($callStart === false) {
                continue;
            }

            $name = substr($part, 0, $callStart);
            $argsString = substr($part, $callStart + 1, -1);
            $args = $this->parseArgs($argsString);

            switch ($name) {
                case 'creerListe':
                    if (count($args) < 2) {
                        continue 2;
                    }
                    $names = array_merge($names, $this->nameRange((int) $args[0], (int) $args[1] + 1, $maxEpisodes));
                    break;

                case 'finirListe':
                case 'finirListeOP':
                    if ($args === []) {
                        break 2;
                    }
                    $start = (int) $args[0];
                    $names = array_merge(
                        $names,
                        $this->nameRange($start, $start + $numberOfEpisodes - count($names), $maxEpisodes)
                    );
                    break 2;

                case 'newSP':
                    if ($args === []) {
                        continue 2;
                    }
                    $names[] = 'Episode '.$args[0];
                    break;

                case 'newSPF':
                    if ($args === []) {
                        continue 2;
                    }
                    $names[] = (string) $args[0];
                    break;

                default:
                    // Unknown function: skip rather than crash.
                    continue 2;
            }
        }

        return $names;
    }

    /**
     * Merge a language page's (names, players) into the running episode list,
     * matching by name and preserving order. Port of `_extend_episodes`.
     *
     * @param  list<array{name: string, languages: array<string, Players>}>  $current
     * @param  list<string>  $names
     * @param  list<Players>  $playersList
     * @return list<array{name: string, languages: array<string, Players>}>
     */
    protected function mergeEpisodes(array $current, string $langId, array $names, array $playersList): array
    {
        $fusion = [];
        $currDone = 0;
        $count = min(count($names), count($playersList));

        for ($i = 0; $i < $count; $i++) {
            $nameNew = $names[$i];
            $players = $playersList[$i];
            $found = false;

            $remaining = count($current) - $currDone;
            for ($pos = 0; $pos < $remaining; $pos++) {
                $absolute = $currDone + $pos;
                if ($current[$absolute]['name'] === $nameNew) {
                    $current[$absolute]['languages'][$langId] = $players;
                    for ($k = $currDone; $k <= $absolute; $k++) {
                        $fusion[] = $current[$k];
                    }
                    $currDone = $absolute + 1;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $fusion[] = ['name' => $nameNew, 'languages' => [$langId => $players]];
            }
        }

        for ($k = $currDone; $k < count($current); $k++) {
            $fusion[] = $current[$k];
        }

        return $fusion;
    }

    // ---------------------------------------------------------------------
    // Homepage section extraction
    // ---------------------------------------------------------------------

    protected function homepageSection(string $sectionName, int $howMany = 1): string
    {
        $homepage = $this->getOrNull($this->siteUrl);
        if (! ($homepage instanceof Response) || $homepage->failed()) {
            return '';
        }

        $sections = explode('<!--', $homepage->body());
        foreach ($sections as $index => $section) {
            $commentEnd = strpos($section, '-->');
            $head = $commentEnd === false ? substr($section, 0, -1) : substr($section, 0, $commentEnd);
            if (str_contains($head, $sectionName)) {
                $slice = array_slice($sections, $index, $howMany);

                return '<!--'.implode('<!--', $slice);
            }
        }

        return '';
    }

    // ---------------------------------------------------------------------
    // Small utilities
    // ---------------------------------------------------------------------

    protected function request(): PendingRequest
    {
        return $this->http
            ->timeout($this->timeout)
            ->withHeaders(['User-Agent' => $this->userAgent]);
    }

    protected function poolRequest(Pool $pool, string $key): PendingRequest
    {
        return $pool->as($key)
            ->timeout($this->timeout)
            ->withHeaders(['User-Agent' => $this->userAgent]);
    }

    protected function get(string $url): Response
    {
        try {
            return $this->request()->get($url);
        } catch (ConnectionException $e) {
            throw new AnimeSamaException('anime-sama is unreachable.', 503, $e);
        }
    }

    protected function getOrNull(string $url): ?Response
    {
        try {
            return $this->request()->get($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    protected function firstMatch(string $pattern, string $subject): string
    {
        return preg_match($pattern, $subject, $match) === 1 ? $match[1] : '';
    }

    protected function unescape(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected function filterAllowed(array $values, array $allowed): array
    {
        return array_values(array_filter(
            array_map('trim', $values),
            static fn (string $value): bool => in_array($value, $allowed, true)
        ));
    }

    protected function removeSomeJsComments(string $string): string
    {
        $string = preg_replace('#/\*[\W\w]*?\*/#', '', $string) ?? $string;

        return preg_replace('#<!--[\W\w]*?-->#', '', $string) ?? $string;
    }

    /**
     * @param  list<string>  $delimiters
     * @return list<string>
     */
    protected function splitAndStrip(string $string, array $delimiters): array
    {
        $list = [$string];
        foreach ($delimiters as $delimiter) {
            $next = [];
            foreach ($list as $part) {
                foreach (explode($delimiter, $part) as $piece) {
                    $next[] = $piece;
                }
            }
            $list = $next;
        }

        return array_map('trim', $list);
    }

    /**
     * Parse a JS call argument string into a list of scalars.
     *
     * @return list<int|float|string>
     */
    protected function parseArgs(string $argsString): array
    {
        if (trim($argsString) === '') {
            return [];
        }

        $tokens = [];
        $buffer = '';
        $quote = null;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argsString[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ',') {
                $tokens[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $tokens[] = $buffer;
        }

        $args = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $firstChar = $token[0];
            $lastChar = $token[strlen($token) - 1];
            if (($firstChar === "'" && $lastChar === "'") || ($firstChar === '"' && $lastChar === '"')) {
                $args[] = substr($token, 1, -1);
            } elseif (is_numeric($token)) {
                $args[] = $token + 0;
            } else {
                $args[] = $token;
            }
        }

        return $args;
    }

    /**
     * @return list<string>
     */
    protected function nameRange(int $start, int $stop, int $maxEpisodes): array
    {
        $names = [];
        for ($n = $start; $n < $stop; $n++) {
            $names[] = 'Episode '.$n.$this->padding($n, $maxEpisodes);
        }

        return $names;
    }

    protected function padding(int $n, int $maxEpisodes): string
    {
        return str_repeat(' ', max(0, strlen((string) $maxEpisodes) - strlen((string) $n)));
    }

    /**
     * Split a comma-separated string into a trimmed list of non-empty values.
     *
     * @return list<string>
     */
    protected function splitList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }

    protected function ensureSlash(string $url): string
    {
        return str_ends_with($url, '/') ? $url : $url.'/';
    }

    protected function lastSegment(string $url): string
    {
        $segments = explode('/', rtrim($url, '/'));

        return end($segments) ?: '';
    }

    protected function nthSegmentFromEnd(string $url, int $n): string
    {
        $segments = explode('/', rtrim($url, '/'));
        $index = count($segments) - $n;

        return $index >= 0 ? ($segments[$index] ?? '') : '';
    }
}
