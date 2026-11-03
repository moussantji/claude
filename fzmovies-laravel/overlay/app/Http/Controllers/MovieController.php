<?php

namespace App\Http\Controllers;

use App\Services\FzMovies\FzMoviesScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-rendered (Blade) frontend. Uses the PHP scraper directly - no
 * external Python service, no Docker.
 */
class MovieController extends Controller
{
    public function index(Request $request, FzMoviesScraper $scraper)
    {
        $q = trim((string) $request->query('q', ''));
        $searchby = $request->query('searchby', 'Name');
        $category = $request->query('category', 'All');

        $movies = [];
        $error = null;

        $nextPage = null;

        if ($q !== '') {
            try {
                $data = $scraper->search($q, $searchby, $category);
                $movies = $data['movies'] ?? [];
                $nextPage = $data['next_page'] ?? null;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('movies.index', compact('q', 'searchby', 'category', 'movies', 'error', 'nextPage'));
    }

    public function browse(Request $request, FzMoviesScraper $scraper)
    {
        $filter = $request->query('filter', 'recently_released');
        $category = $request->query('category', 'Hollywood');
        $genre = $request->query('genre', 'Action');

        $movies = [];
        $error = null;

        $nextPage = null;

        try {
            $data = $scraper->browse($filter, [
                'category' => $category,
                'genre' => $genre,
            ]);
            $movies = $data['movies'] ?? [];
            $nextPage = $data['next_page'] ?? null;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.browse', compact('filter', 'category', 'genre', 'movies', 'error', 'nextPage'));
    }

    /**
     * AJAX endpoint for infinite scroll: fetch the next listing page and
     * return rendered movie cards + the following page URL.
     */
    public function more(Request $request, FzMoviesScraper $scraper)
    {
        $next = (string) $request->query('next', '');
        if ($next === '') {
            return response()->json(['html' => '', 'next' => null]);
        }

        try {
            $data = $scraper->page($next);
        } catch (\Throwable $e) {
            return response()->json(['html' => '', 'next' => null, 'error' => $e->getMessage()]);
        }

        $html = view('partials.movie-cards', ['movies' => $data['movies'] ?? []])->render();

        return response()->json([
            'html' => $html,
            'next' => $data['next_page'] ?? null,
        ]);
    }

    /**
     * Player page: resolve the file to a direct URL, stash it in the session
     * behind a random token, and render an HTML5 <video> player.
     */
    public function watch(Request $request, FzMoviesScraper $scraper)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Film');
        $mirror = (int) $request->query('mirror', 0);

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $token = null;
        $filename = null;
        $error = null;

        try {
            $playable = $scraper->resolvePlayable($url, $mirror);
            $filename = $playable['filename'] ?? $title;
            $token = Str::random(48);
            // Only URLs we resolved server-side can be streamed (no SSRF).
            $request->session()->put('stream.' . $token, $playable['url']);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.watch', compact('title', 'filename', 'token', 'error'));
    }

    /**
     * Streaming proxy. Streams a previously-resolved file URL (looked up by
     * token from the session) with HTTP Range support so the browser can seek.
     */
    public function stream(Request $request, string $token): StreamedResponse
    {
        $source = $request->session()->get('stream.' . $token);
        abort_if(! $source, 404);

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0',
            'Referer' => (string) config('fzmovies.referer', 'https://fzmovies.net/'),
        ];
        if ($range = $request->header('Range')) {
            $headers['Range'] = $range;
        }

        $upstream = Http::withHeaders($headers)
            ->withOptions(['stream' => true, 'allow_redirects' => true])
            ->timeout((int) config('fzmovies.stream_timeout', 0))
            ->get($source);

        $psr = $upstream->toPsrResponse();
        $body = $psr->getBody();

        $status = $upstream->status() === 206 || $range ? $upstream->status() : 200;

        $passthrough = ['Content-Type', 'Content-Length', 'Content-Range', 'Accept-Ranges'];
        $responseHeaders = ['Accept-Ranges' => 'bytes'];
        foreach ($passthrough as $h) {
            if ($psr->hasHeader($h)) {
                $responseHeaders[$h] = $psr->getHeaderLine($h);
            }
        }
        if (empty($responseHeaders['Content-Type'])) {
            $responseHeaders['Content-Type'] = 'video/mp4';
        }

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(1024 * 256);
                flush();
            }
        }, $status, $responseHeaders);
    }

    public function show(Request $request, FzMoviesScraper $scraper)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Movie');
        $year = (int) $request->query('year', 0);

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $files = [];
        $trailer = null;
        $recommended = [];
        $error = null;

        try {
            $data = $scraper->movieFiles($url);
            $files = $data['files'] ?? [];
            $trailer = $data['trailer'] ?? null;
            $recommended = $data['recommended'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.show', compact('title', 'year', 'url', 'files', 'trailer', 'recommended', 'error'));
    }

    public function download(Request $request, FzMoviesScraper $scraper)
    {
        $url = (string) $request->query('url', '');
        $title = (string) $request->query('title', 'Movie');

        if ($url === '') {
            return redirect()->route('movies.index');
        }

        $download = null;
        $error = null;

        try {
            $download = $scraper->downloadLinks($url);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('movies.download', compact('title', 'download', 'error'));
    }
}
