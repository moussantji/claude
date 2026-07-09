<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodeLink;
use App\Models\Season;
use Illuminate\Console\Command;

/**
 * Show row counts for the normalized library tables.
 */
class LibraryStatus extends Command
{
    protected $signature = 'library:status';

    protected $description = 'Show counts for the normalized library tables.';

    public function handle(): int
    {
        $this->table(['Table', 'Rows'], [
            ['animes', Anime::count()],
            ['seasons', Season::count()],
            ['episodes', Episode::count()],
            ['episode_links', EpisodeLink::count()],
        ]);

        $latest = Anime::query()->latest('last_synced_at')->value('name');
        if ($latest) {
            $this->line('Last synced anime: '.$latest);
        }

        return self::SUCCESS;
    }
}
