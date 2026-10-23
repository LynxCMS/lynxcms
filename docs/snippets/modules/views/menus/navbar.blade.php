{{-- A two-level navigation bar in Tailwind, without JavaScript: submenus open with <details>. --}}
<nav aria-label="{{ $menu->title }}" {{ $attributes->merge(['class' => 'border-b']) }}>
    <ul class="flex gap-6">
        @foreach ($menu->items as $item)
            <li @class(['relative', 'font-semibold' => $item->isInActiveTrail])>
                @if ($item->hasChildren())
                    <details class="group">
                        <summary class="cursor-pointer list-none py-3">{{ $item->label }}</summary>
                        <ul class="absolute left-0 grid min-w-48 gap-1 rounded-lg border bg-white p-2 shadow">
                            @if ($item->hasUrl())
                                <li><a href="{{ $item->url }}" @if ($item->isActive) aria-current="page" @endif>All of {{ $item->label }}</a></li>
                            @endif
                            @foreach ($item->children as $child)
                                <li>
                                    @if ($child->hasUrl())
                                        <a href="{{ $child->url }}" @if ($child->target) target="{{ $child->target }}" @endif @if ($child->rel) rel="{{ $child->rel }}" @endif
                                           @if ($child->isActive) aria-current="page" @endif
                                           @class(['block rounded px-2 py-1', 'bg-gray-100' => $child->isActive])>{{ $child->label }}</a>
                                    @else
                                        <span class="block px-2 py-1 text-sm text-gray-500">{{ $child->label }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @elseif ($item->hasUrl())
                    <a href="{{ $item->url }}" class="block py-3" @if ($item->target) target="{{ $item->target }}" @endif @if ($item->rel) rel="{{ $item->rel }}" @endif
                       @if ($item->isActive) aria-current="page" @endif>{{ $item->label }}</a>
                @else
                    <span class="block py-3">{{ $item->label }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
