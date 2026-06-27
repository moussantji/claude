<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Channel;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ChannelBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'cat')]
    public ?int $categoryId = null;

    #[Url(as: 'quality')]
    public string $quality = '';

    public function updating($name): void
    {
        // Reset pagination whenever a filter changes.
        if (in_array($name, ['search', 'categoryId', 'quality'], true)) {
            $this->resetPage();
        }
    }

    public function selectCategory(?int $id): void
    {
        $this->categoryId = $id;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'quality']);
        $this->resetPage();
    }

    public function render()
    {
        $channels = Channel::query()
            ->active()
            ->with('category')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->quality, fn ($q) => $q->where('quality', $this->quality))
            ->orderBy('name')
            ->paginate(18);

        return view('livewire.channel-browser', [
            'channels' => $channels,
            'categories' => Category::withCount(['channels' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
