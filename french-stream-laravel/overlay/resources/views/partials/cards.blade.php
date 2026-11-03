{{-- Loop of cards (reused by grids and the /more AJAX endpoint). Expects $items. --}}
@foreach ($items as $item)
    @include('partials.card', ['item' => $item])
@endforeach
