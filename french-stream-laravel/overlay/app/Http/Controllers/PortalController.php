<?php

namespace App\Http\Controllers;

use App\Services\FrenchStream\FsScraper;
use Illuminate\Http\Request;

/**
 * Visual portal for french-stream.one. Server-rendered Blade + PHP scraper.
 */
class PortalController extends Controller
{
    /** Curated browse targets shown in the navigation. */
    public const MENU = [
        'films' => ['title' => 'Films', 'path' => '/films/'],
        'films-vf' => ['title' => 'Films VF', 'path' => '/films/vf/'],
        'series' => ['title' => 'Séries', 'path' => '/s-tv/'],
        'series-vf' => ['title' => 'Séries VF', 'path' => '/s-tv/s-vf/'],
        'action' => ['title' => 'Action', 'path' => '/films/actions/'],
        'comedie' => ['title' => 'Comédie', 'path' => '/films/comedies/'],
        'horreur' => ['title' => 'Horreur', 'path' => '/films/epouvante-horreurs/'],
        'sf' => ['title' => 'Science-Fiction', 'path' => '/films/science-fictions/'],
        '2026' => ['title' => 'Films 2026', 'path' => '/xfsearch/date-de-sortie/2026/'],
        '2025' => ['title' => 'Films 2025', 'path' => '/xfsearch/date-de-sortie/2025/'],
    ];

    public function home(FsScraper $scraper)
    {
        $sections = [];
        $error = null;
        try {
            $sections = $scraper->home();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('portal.home', ['sections' => $sections, 'error' => $error]);
    }

    public function browse(Request $request, FsScraper $scraper)
    {
        $key = (string) $request->query('c', 'films');
        $entry = self::MENU[$key] ?? self::MENU['films'];

        $items = [];
        $next = null;
        $error = null;
        try {
            $data = $scraper->listing($entry['path']);
            $items = $data['items'];
            $next = $data['next'];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('portal.list', [
            'title' => $entry['title'],
            'activeKey' => $key,
            'items' => $items,
            'next' => $next,
            'error' => $error,
        ]);
    }

    public function search(Request $request, FsScraper $scraper)
    {
        $q = trim((string) $request->query('q', ''));

        $items = [];
        $next = null;
        $error = null;
        if ($q !== '') {
            try {
                $data = $scraper->search($q);
                $items = $data['items'];
                $next = $data['next'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('portal.search', compact('q', 'items', 'next', 'error'));
    }

    public function detail(Request $request, FsScraper $scraper, string $id)
    {
        // Rich data carried from the card (fast, no extra request needed).
        $card = [
            'title' => $request->query('title'),
            'poster' => $request->query('poster'),
            'trailer' => $request->query('trailer'),
            'synopsis' => $request->query('synopsis'),
            'version' => $request->query('version'),
            'quality' => $request->query('quality'),
        ];

        $detail = null;
        $error = null;
        try {
            $detail = $scraper->detail($id);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('portal.detail', compact('id', 'card', 'detail', 'error'));
    }

    /**
     * Infinite scroll: fetch the next listing page, return rendered cards + next.
     */
    public function more(Request $request, FsScraper $scraper)
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

        $html = view('partials.cards', ['items' => $data['items']])->render();

        return response()->json(['html' => $html, 'next' => $data['next']]);
    }
}
