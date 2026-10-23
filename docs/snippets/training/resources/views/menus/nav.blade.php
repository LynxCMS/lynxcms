{{-- The main menu as Bloom's pill links. Editors change it in the admin's Menus screen. --}}
<nav aria-label="{{ $menu->title }}" {{ $attributes->merge(['class' => 'items-center gap-0.5 text-sm']) }}>
    @foreach ($menu->items as $item)
        @if ($item->hasUrl())
            <a href="{{ $item->url }}" @if ($item->isActive) aria-current="page" @endif
               @class(['rounded-full px-3.5 py-1.5 font-medium transition-colors hover:bg-black/5', 'bg-black/5' => $item->isInActiveTrail])>{{ $item->label }}</a>
        @endif
    @endforeach
</nav>
