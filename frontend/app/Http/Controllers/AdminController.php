<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\AnimeTask;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\MediaUpload;
use App\Models\Season;
use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use App\Models\TorrentFileTarget;
use App\Models\TransferProgress;
use App\Models\User;
use App\Services\TorrentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Admin area: manage the normalized library (animes/seasons/episodes/links),
 * including editing the synopsis and setting a trailer.
 */
class AdminController extends Controller
{
    /**
     * Row counts across the app's data.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => [
            'animes' => Anime::count(),
            'seasons' => Season::count(),
            'episodes' => Episode::count(),
            'episode_links' => EpisodeLink::count(),
            'users' => User::count(),
            'uploads' => MediaUpload::count(),
            'requests' => \App\Models\ContentRequest::where('status', 'pending')->count(),
        ]]);
    }

    /**
     * Paginated, searchable list of animes.
     */
    public function animes(Request $request): JsonResponse
    {
        $query = Anime::query()->withCount('seasons');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->paginate(min(100, (int) $request->query('per_page', 30)))
        );
    }

    /**
     * A single anime with its seasons (and episode counts).
     */
    public function showAnime(Anime $anime): JsonResponse
    {
        $anime->load(['seasons' => fn($q) => $q->withCount('episodes')->orderBy('position')]);

        return response()->json(['data' => $anime]);
    }

    /**
     * Update editable fields: name, synopsis, image, trailer, mature flag.
     */
    public function updateAnime(Request $request, Anime $anime): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'synopsis' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'trailer_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_mature' => ['sometimes', 'boolean'],
            'genres' => ['sometimes', 'nullable', 'array'],
            'genres.*' => ['string', 'max:100'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string', 'max:100'],
        ]);

        $anime->fill($validated)->save();

        return response()->json(['data' => $anime->fresh()]);
    }

    public function storeAnime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'synopsis'    => ['sometimes', 'nullable', 'string', 'max:20000'],
            'image_url'   => ['sometimes', 'nullable', 'string', 'max:2048'],
            'trailer_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_mature'   => ['sometimes', 'boolean'],
            'genres'      => ['sometimes', 'nullable', 'array'],
            'genres.*'    => ['string', 'max:100'],
            'categories'  => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string', 'max:100'],
        ]);

        $slug = Str::slug($validated['name']) . '-' . Str::lower(Str::random(6));

        $anime = Anime::create([
            ...$validated,
            'slug' => $slug,
            'url'  => 'local://anime/' . $slug,
        ]);

        return response()->json(['data' => $anime], 201);
    }

    public function storeSeason(Request $request, Anime $anime): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $position = $validated['position'] ?? ((int) $anime->seasons()->max('position') + 1);

        $season = $anime->seasons()->create([
            'name'     => $validated['name'],
            'position' => $position,
            'url'      => 'local://anime/' . $anime->slug . '/saison/' . $position . '-' . Str::lower(Str::random(4)),
        ]);

        return response()->json(['data' => $season], 201);
    }

    public function storeEpisode(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'long_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'index'      => ['sometimes', 'integer', 'min:1'],
        ]);

        $index = $validated['index'] ?? ((int) $season->episodes()->max('index') + 1);

        $episode = $season->episodes()->create([
            'index'      => $index,
            'name'       => $validated['name'] ?? null,
            'long_name'  => $validated['long_name'] ?? null,
            'short_name' => $validated['short_name'] ?? null,
        ]);

        return response()->json(['data' => $episode], 201);
    }

    /**
     * Delete an anime (cascades to its seasons, episodes and links).
     */
    public function destroyAnime(Anime $anime): JsonResponse
    {
        $anime->delete();

        return response()->json(['message' => 'Anime deleted.']);
    }

    /**
     * Episodes (with their links) for a season.
     */
    public function seasonEpisodes(Season $season): JsonResponse
    {
        $season->load(['episodes' => fn($q) => $q->with('links')->orderBy('index')]);

        return response()->json(['data' => $season]);
    }

    /* ---- Seasons ---- */

    public function updateSeason(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $season->fill($validated)->save();

        return response()->json(['data' => $season->fresh()]);
    }

    public function destroySeason(Season $season): JsonResponse
    {
        $season->delete();

        return response()->json(['message' => 'Season deleted.']);
    }

    /* ---- Episodes ---- */

    public function updateEpisode(Request $request, Episode $episode): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'index' => ['sometimes', 'integer', 'min:1'],
        ]);

        $episode->fill($validated)->save();

        return response()->json(['data' => $episode->fresh()]);
    }

    public function destroyEpisode(Episode $episode): JsonResponse
    {
        $episode->delete();

        return response()->json(['message' => 'Episode deleted.']);
    }

    /* ---- Episode video links ---- */

    public function episodeLinks(Episode $episode): JsonResponse
    {
        return response()->json(['data' => $episode->links()->orderBy('position')->get()]);
    }

    public function storeLink(Request $request, Episode $episode): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'max:16'],
            'url' => ['required', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $link = $episode->links()->create([
            'language' => $validated['language'],
            'url' => $validated['url'],
            'position' => $validated['position'] ?? ((int) $episode->links()->max('position') + 1),
            'host' => $this->host($validated['url']),
        ]);

        return response()->json(['data' => $link], 201);
    }

    public function updateLink(Request $request, EpisodeLink $link): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['sometimes', 'string', 'max:16'],
            'url' => ['sometimes', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($validated['url'])) {
            $validated['host'] = $this->host($validated['url']);
        }

        $link->fill($validated)->save();

        return response()->json(['data' => $link->fresh()]);
    }

    public function destroyLink(EpisodeLink $link): JsonResponse
    {
        $link->delete();

        return response()->json(['message' => 'Link deleted.']);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }

    /* ---- Planning ---- */

    public function planningIndex(): JsonResponse
    {
        $entries = \App\Models\Planning::query()
            ->with('anime:id,name,image_url')
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function storePlanning(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anime_id'    => ['required', 'exists:animes,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'time'        => ['sometimes', 'nullable', 'string', 'max:5'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $entry = \App\Models\Planning::create($validated);

        return response()->json(['data' => $entry->load('anime:id,name,image_url')], 201);
    }

    public function updatePlanning(Request $request, \App\Models\Planning $planning): JsonResponse
    {
        $validated = $request->validate([
            'anime_id'    => ['sometimes', 'exists:animes,id'],
            'day_of_week' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'time'        => ['sometimes', 'nullable', 'string', 'max:5'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $planning->fill($validated)->save();

        return response()->json(['data' => $planning->fresh()->load('anime:id,name,image_url')]);
    }

    public function destroyPlanning(\App\Models\Planning $planning): JsonResponse
    {
        $planning->delete();

        return response()->json(['message' => 'Planning entry deleted.']);
    }

    /* ---- Content Requests ---- */

    public function contentRequests(): JsonResponse
    {
        $requests = \App\Models\ContentRequest::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function updateContentRequest(Request $request, \App\Models\ContentRequest $contentRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $contentRequest->status = $validated['status'];
        $contentRequest->save();

        return response()->json(['data' => $contentRequest->fresh()->load('user:id,name,email')]);
    }

    public function destroyContentRequest(\App\Models\ContentRequest $contentRequest): JsonResponse
    {
        $contentRequest->delete();

        return response()->json(['message' => 'Request deleted.']);
    }

    /* ---- Episode Transfers (episodes:auto / :quota) ---- */

    /**
     * Overall transfer progress + today's throughput + per-anime error counts.
     */
    public function transferStats(): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $total = TransferProgress::count();
        $done = TransferProgress::where('status', 'done')->count();
        $pending = TransferProgress::where('status', 'pending')->count();
        $failed = TransferProgress::where('status', 'failed')->count();
        $queued = TransferProgress::where('status', 'queued')->count();
        $doneToday = TransferProgress::where('status', 'done')
            ->whereDate('transferred_at', $today)->count();

        // Per-host remote-upload usage today (from the cache counters set by episodes:remote)
        $perHost = [];
        foreach (['doodstream', 'streamtape', 'streamwish', 'vidhide'] as $p) {
            $used = (int) \Illuminate\Support\Facades\Cache::get("remote_daily:{$p}:{$today}", 0);
            $limitEnv = env('TRANSFER_'.strtoupper($p).'_DAILY_LIMIT');
            $perHost[] = [
                'host' => $p,
                'used_today' => $used,
                'limit' => ($limitEnv !== null && $limitEnv !== '') ? (int) $limitEnv : null,
            ];
        }

        // Animes that currently have at least one failed transfer
        $animesWithErrors = TransferProgress::query()
            ->selectRaw('anime_id, COUNT(*) as errors')
            ->where('status', 'failed')
            ->groupBy('anime_id')
            ->orderByDesc('errors')
            ->limit(100)
            ->with('anime:id,name')
            ->get()
            ->map(fn ($r) => [
                'anime_id' => $r->anime_id,
                'anime' => $r->anime?->name ?? ('#'.$r->anime_id),
                'errors' => (int) $r->errors,
            ]);

        return response()->json(['data' => [
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'queued' => $queued,
            'failed' => $failed,
            'done_today' => $doneToday,
            'percent' => $total > 0 ? round($done / $total * 100, 1) : 0,
            'per_host' => $perHost,
            'animes_with_errors' => $animesWithErrors,
        ]]);
    }

    /**
     * Detailed list of failed transfers (with anime / episode / host / error).
     */
    public function transferErrors(Request $request): JsonResponse
    {
        $query = TransferProgress::query()
            ->where('status', 'failed')
            ->with(['episodeLink.episode.season', 'anime:id,name']);

        if ($animeId = $request->query('anime_id')) {
            $query->where('anime_id', (int) $animeId);
        }

        $items = $query->orderByDesc('last_attempt_at')
            ->limit(300)
            ->get()
            ->map(function (TransferProgress $p) {
                $ep = $p->episodeLink?->episode;
                $season = $ep?->season;

                return [
                    'id' => $p->id,
                    'anime' => $p->anime?->name ?? ('#'.$p->anime_id),
                    'anime_id' => $p->anime_id,
                    'season' => ($season?->position ?? 0) + 1,
                    'episode' => $ep?->index,
                    'language' => $p->episodeLink?->language,
                    'host' => $p->host,
                    'attempts' => $p->attempts,
                    'error' => $p->error,
                    'last_attempt_at' => $p->last_attempt_at,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * Re-arm failed transfers so they get retried (all, or one anime).
     */
    public function retryTransfers(Request $request): JsonResponse
    {
        $q = TransferProgress::where('status', 'failed');
        if ($animeId = $request->input('anime_id')) {
            $q->where('anime_id', (int) $animeId);
        }

        $count = $q->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);

        return response()->json(['message' => "{$count} transfert(s) réarmé(s).", 'count' => $count]);
    }

    /**
     * Re-do finished transfers: put completed ones back in the queue (at the end).
     * Optionally scope to a single anime.
     */
    public function redoTransfers(Request $request): JsonResponse
    {
        $q = TransferProgress::where('status', 'done');
        if ($animeId = $request->input('anime_id')) {
            $q->where('anime_id', (int) $animeId);
        }

        $count = $q->update([
            'status' => 'pending',
            'attempts' => 0,
            'transferred_at' => null,
            'error' => null,
        ]);

        return response()->json(['message' => "{$count} transfert(s) terminé(s) remis en file.", 'count' => $count]);
    }

    /* ---- Team manual task tracking (download/upload done by hand) ---- */

    /** Animes with more episodes than this are flagged "big" (do later). */
    private const BIG_EPISODES = 25;

    /** SQL that counts episodes of the current anime row. */
    private function bigEpisodesExpr(): string
    {
        return '(select count(*) from episodes e join seasons s on s.id = e.season_id where s.anime_id = animes.id)';
    }

    /**
     * Counters for the team dashboard.
     */
    public function tasksStats(): JsonResponse
    {
        $total = Anime::count();
        $done = AnimeTask::where('status', 'done')->count();
        $inProgress = AnimeTask::where('status', 'in_progress')->count();
        $modified = AnimeTask::where('is_modified', true)->count();
        $deferred = AnimeTask::where('is_deferred', true)->count();
        $big = Anime::whereRaw($this->bigEpisodesExpr().' > ?', [self::BIG_EPISODES])->count();

        return response()->json(['data' => [
            'total' => $total,
            'todo' => max(0, $total - $done - $inProgress),
            'in_progress' => $inProgress,
            'done' => $done,
            'modified' => $modified,
            'deferred' => $deferred,
            'big' => $big,
        ]]);
    }

    /**
     * Paginated anime list with episode count, +25 flag and manual task status.
     * Filters: ?filter=todo|in_progress|done|modified|deferred|big  &  ?q=name
     */
    public function tasksIndex(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));
        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', '');

        $query = Anime::query()->withCount('episodes')->with('task');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        switch ($filter) {
            case 'todo':
                $query->where(function ($w) {
                    $w->whereDoesntHave('task')
                      ->orWhereHas('task', fn ($t) => $t->where('status', 'todo'));
                });
                break;
            case 'in_progress':
                $query->whereHas('task', fn ($t) => $t->where('status', 'in_progress'));
                break;
            case 'done':
                $query->whereHas('task', fn ($t) => $t->where('status', 'done'));
                break;
            case 'modified':
                $query->whereHas('task', fn ($t) => $t->where('is_modified', true));
                break;
            case 'deferred':
                $query->whereHas('task', fn ($t) => $t->where('is_deferred', true));
                break;
            case 'big':
                $query->whereRaw($this->bigEpisodesExpr().' > ?', [self::BIG_EPISODES]);
                break;
        }

        $paginated = $query->orderBy('id')->paginate($perPage);

        $data = collect($paginated->items())->map(function (Anime $a) {
            $t = $a->task;

            return [
                'id' => $a->id,
                'name' => $a->name,
                'image_url' => $a->image_url,
                'episodes_count' => (int) $a->episodes_count,
                'is_big' => (int) $a->episodes_count > self::BIG_EPISODES,
                'status' => $t->status ?? 'todo',
                'is_modified' => (bool) ($t->is_modified ?? false),
                'is_deferred' => (bool) ($t->is_deferred ?? false),
                'assigned_to' => $t->assigned_to ?? null,
                'note' => $t->note ?? null,
                'updated_by' => $t->updated_by ?? null,
                'updated_at' => $t?->updated_at?->toDateTimeString(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'big_threshold' => self::BIG_EPISODES,
        ]);
    }

    /**
     * Create/update the manual task status for one anime.
     */
    public function updateTask(Request $request, Anime $anime): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:todo,in_progress,done'],
            'is_modified' => ['nullable', 'boolean'],
            'is_deferred' => ['nullable', 'boolean'],
            'assigned_to' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $task = AnimeTask::firstOrNew(['anime_id' => $anime->id]);

        foreach (['status', 'is_modified', 'is_deferred', 'assigned_to', 'note'] as $field) {
            if ($request->has($field)) {
                $task->{$field} = $validated[$field] ?? (in_array($field, ['is_modified', 'is_deferred'], true) ? false : null);
            }
        }

        $task->updated_by = $request->user()?->name;
        $task->save();

        return response()->json(['data' => $task, 'message' => 'Enregistré']);
    }

    /* ====================================================================
     * TORRENT REMOTE-UPLOAD (send download links to the player host)
     *
     * The admin submits a magnet / .torrent (resolved via Real-Debrid into
     * direct HTTP links) or pastes direct links. Each link is sent to the
     * target host which fetches it itself (remote upload). No download happens
     * on our server.
     * ==================================================================== */

    /**
     * List batches (paginated, filterable by status).
     */
    public function torrentIndex(Request $request, TorrentService $service): JsonResponse
    {
        $hasTargets = \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets');

        // Auto-poll: advance batches that are in-flight (processing, pending,
        // downloading, resolving). This replaces the need for a cron/process
        // command when using direct links — the admin just refreshes the page
        // and the remote upload statuses get checked automatically. Each batch
        // gets one processJob() pass per page load (bounded to 5 batches max
        // to keep the response time reasonable).
        if ($hasTargets) {
            $inflightBatches = TorrentDownload::whereIn('status', ['pending', 'processing', 'downloading', 'resolving'])
                ->orderBy('updated_at')
                ->limit(5)
                ->get();
            foreach ($inflightBatches as $batch) {
                try {
                    $service->processJob($batch);
                } catch (\Throwable) {
                    // Don't break the page listing if processJob fails.
                }
            }
        }

        $query = TorrentDownload::query()
            ->withCount('files')
            ->with([
                'season:id,name,anime_id',
                'season.anime:id,name',
                'episode:id,index,name,season_id',
                'episode.season:id,name,position,anime_id',
                'episode.season.anime:id,name',
                'submitter:id,name',
            ]);

        // Count completed uploads (per-player targets with an embed URL) so the
        // UI can show the "Mettre à jour la base" button even for batches whose
        // files_done counter was never advanced (e.g. old / stuck batches).
        if ($hasTargets) {
            $query->withCount(['targets as uploads_done' => function ($q) {
                // Qualify columns: both torrent_files and torrent_file_targets
                // have `status`, so it would be ambiguous in the join.
                $q->where('torrent_file_targets.status', 'done')
                    ->whereNotNull('torrent_file_targets.embed_url');
            }]);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('title', 'like', "%{$q}%");
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $result = $query->orderByDesc('created_at')->paginate($perPage);

        // Ensure uploads_done is always set. If the targets count is 0 (or the
        // multi-target table doesn't exist), fall back to counting files that
        // stored an embed_url directly (legacy single-target batches).
        // Also self-heal: if all work is finished but the batch is stuck in
        // "processing", advance it to "done" so the UI reflects reality.
        $result->getCollection()->transform(function (TorrentDownload $batch) use ($hasTargets) {
            $uploads = (int) ($batch->uploads_done ?? 0);
            if ($uploads === 0) {
                $uploads = $batch->files()
                    ->where('status', 'done')
                    ->whereNotNull('embed_url')
                    ->count();
            }
            $batch->uploads_done = $uploads;

            // Self-heal stuck batches: if the batch is still "processing" but
            // there's nothing pending/queued, advance to "done". This covers the
            // case where the last poll marked everything done but processJob()
            // didn't run again to call markDone().
            if ($batch->status === 'processing') {
                $stillWorking = false;
                if ($hasTargets) {
                    $stillWorking = TorrentFileTarget::whereHas('file', fn ($q) => $q->where('torrent_download_id', $batch->id))
                        ->whereIn('status', ['pending', 'queued', 'uploading'])
                        ->exists();
                }
                if (! $stillWorking) {
                    $stillWorking = $batch->files()->where('status', 'pending')->exists();
                }
                if (! $stillWorking) {
                    $batch->update(['status' => 'done', 'error' => null]);
                    $batch->status = 'done';
                    // Also refresh the file counters so they're accurate.
                    $batch->refreshCounters();
                }
            }

            return $batch;
        });

        return response()->json($result);
    }

    /**
     * Queue stats + platforms + per-host daily upload usage.
     */
    public function torrentStats(TorrentService $service): JsonResponse
    {
        $counts = TorrentDownload::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return response()->json(['data' => [
            'pending' => $counts['pending'] ?? 0,
            'downloading' => $counts['downloading'] ?? 0,
            'resolving' => $counts['resolving'] ?? 0,
            'processing' => $counts['processing'] ?? 0,
            'done' => $counts['done'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'total' => array_sum($counts),
            'links_unmatched' => TorrentFile::where('status', 'skipped')->count(),
            'links_failed' => TorrentFile::where('status', 'failed')->count(),
            // Multi-target: per-player upload results across all batches. Guarded
            // so the panel still works before the torrent_file_targets migration
            // has been run.
            'targets_done' => \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets')
                ? TorrentFileTarget::where('status', 'done')->count() : 0,
            'targets_failed' => \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets')
                ? TorrentFileTarget::where('status', 'failed')->count() : 0,
            'platforms' => TorrentService::remoteHosts(),
            // Active provider + readiness so the UI can guide the admin.
            'provider' => TorrentService::defaultProvider(),
            'aria2_ready' => (new \App\Services\Aria2Client())->isAvailable(),
            'debrid_ready' => (new \App\Services\RealDebridClient())->isConfigured(),
            // Per-host daily upload quota usage (shared with episodes:remote).
            'daily_usage' => $service->dailyUsage(),
            // Whether the multi-target table exists yet (drives the "migrate" hint).
            'migrated' => \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets'),
        ]]);
    }

    /**
     * Run pending database migrations from the admin panel.
     *
     * Useful on shared hosting (no SSH / CLI) where `php artisan migrate`
     * cannot be run manually. Admin-only (protected by the route middleware).
     */
    public function runMigrations(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return response()->json([
                'data' => [
                    'ok' => true,
                    'migrated' => \Illuminate\Support\Facades\Schema::hasTable('torrent_file_targets'),
                    'output' => $output !== '' ? $output : 'Aucune migration en attente.',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Échec de la migration : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Advance the torrent queue on demand, straight from the admin panel.
     *
     * Runs one processing pass over in-flight + pending batches (like the
     * `torrents:process` command). Meant for shared hosting where no persistent
     * worker / cron runs. Each pass is incremental and safe to repeat.
     */
    public function torrentProcessNow(TorrentService $service): JsonResponse
    {
        @set_time_limit(120); // best-effort; shared hosting may cap this

        $limit = 5;

        // In-flight first (aria2 download / RD resolution / upload), then new.
        $jobs = TorrentDownload::whereIn('status', ['downloading', 'resolving', 'processing'])
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $slots = max(0, $limit - $jobs->count());
        if ($slots > 0) {
            $pending = TorrentDownload::where('status', 'pending')
                ->where('attempts', '<', 3)
                ->orderBy('created_at')
                ->limit($slots)
                ->get();
            $jobs = $jobs->concat($pending);
        }

        $results = [];
        foreach ($jobs as $job) {
            // Run multiple passes per batch so that submit + poll + finalize
            // can all happen in one "Traiter maintenant" click (important for
            // multi-player batches with many targets).
            $passes = $job->isProcessing() ? 3 : 1;
            for ($i = 0; $i < $passes; $i++) {
                try {
                    $service->processJob($job);
                } catch (\Throwable $e) {
                    // processJob already marks the job failed; keep going.
                    break;
                }
                $job->refresh();
                if ($job->isDone() || $job->isFailed()) {
                    break;
                }
            }
            $job->refresh();
            $results[] = [
                'id' => $job->id,
                'title' => $job->title ?: ('#' . $job->id),
                'status' => $job->status,
                'files_done' => $job->files_done,
                'files_total' => $job->files_total,
                'error' => $job->error,
            ];
        }

        return response()->json([
            'data' => [
                'processed' => count($results),
                'jobs' => $results,
                'message' => count($results)
                    ? 'File avancée. Relancez tant que des batches sont en cours.'
                    : 'Rien à traiter pour le moment.',
            ],
        ]);
    }

    /**
     * Submit a batch: a magnet/.torrent file/URL (resolved via Real-Debrid)
     * OR direct download links.
     *
     * Body (multipart/form-data or JSON):
     *   target_platform  : doodstream|streamwish|streamtape|vidhide  (required)
     *   target_platforms : array of the above (multi-select)
     *   anime_id         : the anime to associate (auto-resolves title)
     *   season_id        : season pack — files auto-matched to episodes
     *   episode_id       : single episode — everything maps to it
     *   language         : default VOSTFR
     *   title            : optional label (auto-filled from anime if anime_id given)
     *   torrent_url      : a magnet: URI or an http(s) .torrent URL
     *   torrent_file     : uploaded .torrent file (binary, multipart)
     *   links            : OR direct download links (string / array).
     *                      Provide torrent_url OR torrent_file OR links.
     */
    public function torrentStore(Request $request, TorrentService $service): JsonResponse
    {
        $validated = $request->validate([
            'target_platform' => ['nullable', 'string', 'in:doodstream,streamwish,streamtape,vidhide'],
            'target_platforms' => ['nullable', 'array'],
            'target_platforms.*' => ['string', 'in:doodstream,streamwish,streamtape,vidhide'],
            'anime_id' => ['nullable', 'integer', 'exists:animes,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'episode_id' => ['nullable', 'integer', 'exists:episodes,id'],
            'language' => ['nullable', 'string', 'max:16'],
            'title' => ['nullable', 'string', 'max:500'],
            'torrent_url' => ['nullable', 'string', 'max:8192'],
            'torrent_file' => ['nullable', 'file', 'max:10240'], // max 10MB .torrent
            'links' => ['nullable'],
        ]);

        // Target player(s): accept a multi-select list OR a single platform.
        $platforms = array_values(array_unique(array_filter((array) ($validated['target_platforms'] ?? []))));
        if (empty($platforms) && ! empty($validated['target_platform'])) {
            $platforms = [$validated['target_platform']];
        }
        if (empty($platforms)) {
            return response()->json([
                'message' => 'Choisissez au moins un lecteur cible.',
                'errors' => ['target_platforms' => ['Sélectionnez au moins un lecteur.']],
            ], 422);
        }

        if (empty($validated['season_id']) && empty($validated['episode_id'])) {
            return response()->json([
                'message' => 'Indiquez une saison (pack) ou un épisode (unité).',
                'errors' => ['season_id' => ['season_id ou episode_id est requis.']],
            ], 422);
        }

        // If anime_id is provided, auto-resolve title from the anime name.
        $animeId = $validated['anime_id'] ?? null;
        $anime = $animeId ? Anime::find($animeId) : null;
        $title = $validated['title'] ?? null;
        if ($anime && ! $title) {
            $season = isset($validated['season_id'])
                ? Season::find($validated['season_id'])
                : null;
            $title = $season
                ? "{$anime->name} — {$season->name}"
                : $anime->name;
        }

        // Determine torrent source: uploaded file > URL/magnet > direct links
        $torrentUrl = trim((string) ($validated['torrent_url'] ?? ''));
        $torrentFilePath = null;
        $uploadedFile = $request->file('torrent_file');

        if ($uploadedFile && $uploadedFile->isValid()) {
            // Store the uploaded .torrent file to disk for the worker to read.
            $storedPath = $uploadedFile->store('torrents', 'local');
            if (! $storedPath) {
                return response()->json([
                    'message' => 'Impossible de sauvegarder le fichier .torrent.',
                    'errors' => ['torrent_file' => ['Erreur lors du stockage du fichier.']],
                ], 422);
            }
            $torrentFilePath = storage_path('app/' . $storedPath);
        }

        $isTorrent = $torrentFilePath !== null || $torrentUrl !== '';

        // Provider: aria2 by default (VPS downloads directly).
        $provider = TorrentService::defaultProvider();

        // Validate URL format (only when using a URL, not a file upload).
        if ($torrentFilePath === null && $torrentUrl !== '' && ! preg_match('#^(magnet:|https?://)#i', $torrentUrl)) {
            return response()->json([
                'message' => 'Lien invalide.',
                'errors' => ['torrent_url' => ['Fournissez un magnet: ou une URL http(s) vers un .torrent.']],
            ], 422);
        }

        $links = $isTorrent ? [] : $this->normalizeLinks($request->input('links'));
        if (! $isTorrent && empty($links)) {
            return response()->json([
                'message' => 'Aucune source fournie.',
                'errors' => ['links' => ['Fournissez un fichier .torrent, un magnet ou au moins un lien http(s).']],
            ], 422);
        }

        $job = TorrentDownload::create([
            'target_platform' => $platforms[0],        // primary/legacy
            'target_platforms' => $platforms,          // full multi-target list
            'anime_id' => $animeId,
            'season_id' => $validated['season_id'] ?? null,
            'episode_id' => $validated['episode_id'] ?? null,
            'language' => $validated['language'] ?? 'VOSTFR',
            'title' => $title,
            'torrent_url' => $torrentUrl !== '' ? $torrentUrl : null,
            'torrent_file_path' => $torrentFilePath,
            'provider' => $provider,
            'status' => 'pending',
            'submitted_by' => $request->user()?->id,
        ]);

        if ($isTorrent) {
            $source = $torrentFilePath ? 'fichier .torrent uploadé' : 'magnet/URL';
            $providerLabel = $provider === 'aria2' ? 'Le VPS va le télécharger (aria2)' : 'Real-Debrid va le résoudre';
            $message = "Torrent en file ({$source}) — {$providerLabel} puis envoyer les épisodes au lecteur.";
        } else {
            $count = $service->createFilesFromLinks($job, $links);
            $message = "Batch créé — {$count} lien(s) en file.";
        }

        $job->load(['files' => fn ($q) => $q->with('episode:id,index,name')->orderBy('detected_episode')]);

        return response()->json([
            'message' => $message,
            'data' => $job,
        ], 201);
    }

    /**
     * Normalize the `links` input into a list of {url, episode_id}.
     *
     * @return list<array{url:string, episode_id:int|null}>
     */
    private function normalizeLinks(mixed $links): array
    {
        $out = [];

        // Newline / comma separated text blob (from a textarea).
        if (is_string($links)) {
            foreach (preg_split('/[\r\n]+/', $links) ?: [] as $line) {
                $url = trim($line);
                if ($url !== '' && preg_match('#^https?://#i', $url)) {
                    $out[] = ['url' => $url, 'episode_id' => null];
                }
            }

            return $out;
        }

        if (is_array($links)) {
            foreach ($links as $item) {
                if (is_string($item)) {
                    $url = trim($item);
                    if ($url !== '' && preg_match('#^https?://#i', $url)) {
                        $out[] = ['url' => $url, 'episode_id' => null];
                    }
                } elseif (is_array($item) && ! empty($item['url'])) {
                    $url = trim((string) $item['url']);
                    if ($url !== '' && preg_match('#^https?://#i', $url)) {
                        $out[] = [
                            'url' => $url,
                            'episode_id' => isset($item['episode_id']) ? (int) $item['episode_id'] : null,
                        ];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Show a batch with its links (per-episode breakdown).
     */
    public function torrentShow(TorrentDownload $torrent): JsonResponse
    {
        $torrent->load([
            'season:id,name,anime_id',
            'season.anime:id,name',
            'episode:id,index,name,season_id',
            'submitter:id,name',
            'files' => fn ($q) => $q->with(['episode:id,index,name', 'targets'])->orderBy('detected_episode'),
        ]);

        return response()->json(['data' => $torrent]);
    }

    /**
     * List the episodes inside a batch, each with its per-player targets
     * (status + embed URL for every selected player).
     */
    public function torrentFiles(TorrentDownload $torrent): JsonResponse
    {
        $files = $torrent->files()
            ->with(['episode:id,index,name', 'targets'])
            ->orderBy('detected_episode')
            ->get();

        return response()->json(['data' => $files]);
    }

    /**
     * Manually (re)map a link to an episode. Re-queues it if it was
     * skipped/failed and nudges the batch back into processing.
     */
    public function torrentFileUpdate(Request $request, TorrentFile $file): JsonResponse
    {
        $validated = $request->validate([
            'episode_id' => ['required', 'integer', 'exists:episodes,id'],
            'language' => ['sometimes', 'string', 'max:16'],
        ]);

        $file->episode_id = $validated['episode_id'];
        if (isset($validated['language'])) {
            $file->language = $validated['language'];
        }
        if (in_array($file->status, ['skipped', 'failed'], true)) {
            $file->status = 'pending';
            $file->error = null;
            $file->remote_job_id = null;
        }
        $file->save();

        $torrent = $file->torrent;
        if ($torrent && $torrent->status === 'done' && $file->status === 'pending') {
            $torrent->update(['status' => 'processing']);
        }
        $torrent?->refreshCounters();

        return response()->json(['data' => $file->fresh()->load('episode:id,index,name')]);
    }

    /**
     * Manually push the batch's uploaded links into the database.
     *
     * Called from the admin panel button. The admin first verifies on the
     * player(s) that everything is live, THEN clicks to create the
     * episode_links. Idempotent (existing links are skipped).
     */
    public function torrentSyncLinks(TorrentDownload $torrent, TorrentService $service): JsonResponse
    {
        $result = $service->syncLinksToDatabase($torrent);
        $created = $result['links'];
        $organized = $result['organized'];

        $parts = [];
        $parts[] = $created > 0
            ? "{$created} lien(s) ajouté(s) à la base"
            : 'aucun nouveau lien (déjà à jour)';
        if ($organized > 0) {
            $parts[] = "{$organized} fichier(s) rangé(s) dans des dossiers sur le lecteur";
        }

        return response()->json([
            'data' => $torrent->fresh(),
            'message' => ucfirst(implode(' · ', $parts)) . '.',
            'created' => $created,
            'organized' => $organized,
        ]);
    }

    /**
     * Retry a failed batch, or re-arm its failed per-player targets.
     */
    public function torrentRetry(TorrentDownload $torrent): JsonResponse
    {
        if ($torrent->isFailed()) {
            $torrent->retry();

            return response()->json(['data' => $torrent->fresh(), 'message' => 'Batch réarmé.']);
        }

        // Re-arm failed per-player uploads.
        $targets = TorrentFileTarget::whereHas('file', fn ($q) => $q->where('torrent_download_id', $torrent->id))
            ->where('status', 'failed')->get();

        // Re-arm failed files (download-stage failures, e.g. RD unrestrict).
        $failedFiles = $torrent->files()->where('status', 'failed')->count();
        $torrent->files()->where('status', 'failed')
            ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);

        if ($targets->isNotEmpty()) {
            $fileIds = $targets->pluck('torrent_file_id')->unique();
            TorrentFileTarget::whereIn('id', $targets->pluck('id'))
                ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);
            // Put the affected episode files back to pending so they re-finalize.
            TorrentFile::whereIn('id', $fileIds)->update(['status' => 'pending']);
        }

        $count = $targets->count() + $failedFiles;
        if ($count > 0) {
            $torrent->update(['status' => 'processing']);

            return response()->json(['data' => $torrent->fresh(), 'message' => "{$count} upload(s) réarmé(s)."]);
        }

        return response()->json(['message' => 'Rien à réessayer pour ce batch.'], 422);
    }

    /**
     * Retry ONE player target with an OPTIONAL new source link.
     *
     * Lets the admin re-send a single episode to a single player (e.g. retry
     * a player with a fresh link) WITHOUT touching the players that already
     * succeeded. If a new URL is given, it's stored on the target only.
     */
    public function torrentTargetRetry(Request $request, TorrentFileTarget $target): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $newUrl = trim((string) ($validated['source_url'] ?? ''));
        if ($newUrl !== '' && ! preg_match('#^https?://#i', $newUrl)) {
            return response()->json([
                'message' => 'Lien invalide.',
                'errors' => ['source_url' => ['Fournissez une URL http(s) valide.']],
            ], 422);
        }

        $file = $target->file;

        // Remove the OLD episode_link of this player (if it was already synced
        // to the DB) so the stale/dead link is gone before the new upload.
        if ($file && $file->episode_id) {
            $oldEmbed = $target->embed_url;
            $language = $file->language ?? 'VOSTFR';
            $platform = $target->platform;

            EpisodeLink::where('episode_id', $file->episode_id)
                ->where('language', $language)
                ->get()
                ->each(function (EpisodeLink $link) use ($oldEmbed, $platform) {
                    $host = preg_replace('/^www\./', '', (string) parse_url((string) $link->url, PHP_URL_HOST));
                    // Match by exact old embed URL, or by the player host domain.
                    $isSamePlayer = ($oldEmbed && $link->url === $oldEmbed)
                        || ($host !== '' && str_contains($host, $platform))
                        || ($host !== '' && str_contains(strtolower((string) $link->host), $platform));
                    if ($isSamePlayer) {
                        $link->delete();
                    }
                });
        }

        // Re-arm this target only. A new URL (if provided) is stored on the
        // target so it overrides the file's source_url for this player alone.
        $target->update([
            'status' => 'pending',
            'error' => null,
            'remote_job_id' => null,
            'embed_url' => null,
            'file_code' => null,
            'source_url' => $newUrl !== '' ? $newUrl : $target->source_url,
        ]);

        // Put the parent file + batch back into processing so it re-submits.
        if ($file) {
            // Always reset the file to pending so submitPendingTargets picks it up.
            if (in_array($file->status, ['done', 'failed', 'skipped'], true)) {
                $file->update(['status' => 'pending', 'error' => null]);
            }
            $torrent = $file->torrent;
            if ($torrent && ! $torrent->isProcessing()) {
                $torrent->update(['status' => 'processing', 'error' => null]);
            }
            $torrent?->refreshCounters();
        }

        return response()->json([
            'data' => $target->fresh(),
            'message' => $newUrl !== ''
                ? "Lecteur {$target->platform} réarmé avec le nouveau lien."
                : "Lecteur {$target->platform} réarmé.",
        ]);
    }

    /**
     * Deduplicate episode links: keep one link per player per language per
     * episode across the whole catalog (or a single anime with ?anime_id=).
     */
    public function torrentCleanupLinks(Request $request, TorrentService $service): JsonResponse
    {
        $animeId = $request->input('anime_id');
        $keepOnlyManaged = filter_var($request->input('keep_only_managed', false), FILTER_VALIDATE_BOOLEAN);

        $result = $service->deduplicateLinks(
            $animeId !== null ? (int) $animeId : null,
            $keepOnlyManaged,
        );

        $msg = "{$result['removed']} lien(s) supprimé(s) sur {$result['episodes']} épisode(s).";
        if ($keepOnlyManaged && $result['pruned'] > 0) {
            $msg .= " Dont {$result['pruned']} lien(s) de lecteurs non gérés retirés.";
        }

        return response()->json([
            'message' => $msg,
            'removed' => $result['removed'],
            'episodes' => $result['episodes'],
            'pruned' => $result['pruned'],
        ]);
    }

    /**
     * Retry ALL failed batches, files and per-player targets.
     */
    public function torrentRetryAll(): JsonResponse
    {
        $batches = TorrentDownload::where('status', 'failed')
            ->update(['status' => 'pending', 'error' => null]);

        // Failed per-player targets → re-arm, and reset their files + batches.
        $failedTargets = TorrentFileTarget::where('status', 'failed')->get(['id', 'torrent_file_id']);
        $targets = $failedTargets->count();
        if ($targets > 0) {
            TorrentFileTarget::whereIn('id', $failedTargets->pluck('id'))
                ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);

            $fileIds = $failedTargets->pluck('torrent_file_id')->unique();
            TorrentFile::whereIn('id', $fileIds)->update(['status' => 'pending']);

            $batchIds = TorrentFile::whereIn('id', $fileIds)->pluck('torrent_download_id')->unique();
            TorrentDownload::whereIn('id', $batchIds)->where('status', 'done')
                ->update(['status' => 'processing']);
        }

        // Failed files (download-stage) → re-arm too.
        $failedFileBatches = TorrentFile::where('status', 'failed')->pluck('torrent_download_id')->unique();
        $files = TorrentFile::where('status', 'failed')
            ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);
        if ($failedFileBatches->isNotEmpty()) {
            TorrentDownload::whereIn('id', $failedFileBatches)->where('status', 'done')
                ->update(['status' => 'processing']);
        }

        return response()->json([
            'message' => "{$batches} batch(es), {$files} fichier(s) et {$targets} upload(s) réarmé(s).",
            'batches' => $batches,
            'files' => $files,
            'targets' => $targets,
        ]);
    }

    /**
     * Delete a batch (and its links). Completed batches cannot be deleted here
     * (remove the episode links instead).
     */
    public function torrentDestroy(TorrentDownload $torrent): JsonResponse
    {
        if ($torrent->isDone()) {
            return response()->json(['message' => 'Batch terminé — supprimez plutôt les liens des épisodes.'], 422);
        }

        $torrent->delete(); // cascades to torrent_files

        return response()->json(['message' => 'Batch supprimé.']);
    }

    /* ====================================================================
     * RECENTLY ADDED / MODIFIED CONTENT (verify links)
     * ==================================================================== */

    /**
     * Animes recently added or modified, with the episodes that have video
     * links and every player URL — so the admin can click to verify playback.
     *
     * ?q= filters by name, ?per_page=, ?page=.
     */
    public function recentContent(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        // Animes that actually have at least one episode video link, most
        // recently touched first.
        $query = Anime::query()
            ->whereHas('seasons.episodes.links')
            ->withCount(['seasons'])
            ->orderByDesc('updated_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('name', 'like', "%{$q}%");
        }

        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items())->map(function (Anime $anime) {
            // Count links + collect the distinct hosts used across this anime.
            $links = EpisodeLink::query()
                ->whereHas('episode.season', fn ($s) => $s->where('anime_id', $anime->id))
                ->get(['id', 'host']);

            return [
                'id' => $anime->id,
                'name' => $anime->name,
                'image_url' => $anime->image_url,
                'seasons_count' => (int) $anime->seasons_count,
                'links_count' => $links->count(),
                'hosts' => $links->pluck('host')->filter()->unique()->values()->all(),
                'updated_at' => $anime->updated_at?->toDateTimeString(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Every episode link of one anime, grouped by episode, with clickable
     * player URLs (+ a Real-Debrid quick-check link when configured) to verify.
     */
    public function recentContentLinks(Anime $anime): JsonResponse
    {
        $anime->load(['seasons' => fn ($s) => $s->orderBy('position')
            ->with(['episodes' => fn ($e) => $e->orderBy('index')
                ->with(['links' => fn ($l) => $l->orderBy('language')])]),
        ]);

        $rdReady = (new \App\Services\RealDebridClient())->isConfigured();

        $seasons = $anime->seasons->map(function ($season) {
            return [
                'id' => $season->id,
                'name' => $season->name,
                'position' => $season->position,
                'episodes' => $season->episodes
                    ->filter(fn ($ep) => $ep->links->isNotEmpty())
                    ->map(fn ($ep) => [
                        'id' => $ep->id,
                        'index' => $ep->index,
                        'name' => $ep->name,
                        'links' => $ep->links->map(fn ($l) => [
                            'id' => $l->id,
                            'language' => $l->language,
                            'host' => $l->host,
                            'url' => $l->url,
                        ])->values()->all(),
                    ])->values()->all(),
            ];
        })->values()->all();

        return response()->json(['data' => [
            'anime' => ['id' => $anime->id, 'name' => $anime->name, 'image_url' => $anime->image_url],
            'seasons' => $seasons,
            'realdebrid_ready' => $rdReady,
        ]]);
    }
}
