<nav aria-label="{{ $menu->title }}">
    <ul class="space-y-2 text-sm">
        @foreach ($menu->items as $item)
            @if ($item->hasUrl())
                <li><a href="{{ $item->url }}" class="opacity-70 hover:opacity-100">{{ $item->label }}</a></li>
            @endif
        @endforeach
    </ul>
</nav>
