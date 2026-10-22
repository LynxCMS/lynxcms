@if ($livewire !== null)
    @livewire($livewire, $with)
@else
    @include($view, [...$with, 'page' => $page])
@endif
