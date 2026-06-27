<?php

namespace App\Livewire;

use App\Models\Series;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SeriesBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'genre')]
    public string $genre = '';

    #[Url(as: 'sort')]
    public string $sort = 'latest';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'genre', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function setGenre(string $genre): void
    {
        $this->genre = $this->genre === $genre ? '' : $genre;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'genre', 'sort']);
        $this->resetPage();
    }

    public function render()
    {
        $series = Series::query()
            ->active()
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->genre, fn ($q) => $q->where('genre', $this->genre))
            ->when($this->sort === 'rating', fn ($q) => $q->orderByDesc('rating'))
            ->when($this->sort === 'year', fn ($q) => $q->orderByDesc('start_year'))
            ->when($this->sort === 'latest', fn ($q) => $q->latest())
            ->paginate(18);

        return view('livewire.series-browser', [
            'series' => $series,
            'genres' => Series::active()->whereNotNull('genre')->distinct()->orderBy('genre')->pluck('genre'),
        ]);
    }
}
