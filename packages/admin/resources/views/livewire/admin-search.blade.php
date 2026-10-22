{{-- The top bar search (AdminSearch): text searches titles, a selector runs as in code. Cmd/Ctrl+K focuses it. --}}
<div class="relative w-full max-w-md" x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.escape="open = false; $refs.input.blur()"
     @keydown.window.meta.k.prevent="$refs.input.focus(); $refs.input.select()"
     @keydown.window.ctrl.k.prevent="$refs.input.focus(); $refs.input.select()">
    <label for="lynx-admin-search" class="sr-only">Search pages</label>
    <x-lucide-search class="text-muted-foreground pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2" aria-hidden="true" />
    <input id="lynx-admin-search" x-ref="input" type="search" autocomplete="off" spellcheck="false"
           class="blat-input h-8 ps-8 pe-12 text-sm"
           placeholder="Search, or template=course, sort=-modified"
           wire:model.live.debounce.300ms="query"
           @focus="open = true" @input="open = true"
           @keydown.enter.prevent="$refs.results?.querySelector('a')?.click()"
           @keydown.down.prevent="$refs.results?.querySelector('a')?.focus()"
           aria-controls="lynx-admin-search-results" :aria-expanded="open">
    <x-lynx-admin::ui.kbd class="pointer-events-none absolute end-2 top-1/2 hidden -translate-y-1/2 sm:inline-flex" x-text="/Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘K' : 'Ctrl K'">⌘K</x-lynx-admin::ui.kbd>

    @if (trim($query) !== '')
        <div id="lynx-admin-search-results" x-ref="results" x-show="open" x-cloak
             class="bg-popover text-popover-foreground absolute end-0 top-full z-50 mt-1 grid w-[min(32rem,90vw)] gap-1 rounded-md border p-1 shadow-md">
            @if ($error !== null)
                <p role="alert" class="text-destructive px-2 py-1.5 text-sm">{{ $error }}</p>
            @elseif ($results === [])
                <p class="text-muted-foreground px-2 py-1.5 text-sm">No pages found.</p>
            @else
                <ul class="grid">
                    @foreach ($results as $result)
                        <li wire:key="search-{{ $result['id'] }}">
                            <a href="{{ route('lynx.admin.pages.edit', $result['id']) }}"
                               class="hover:bg-accent focus:bg-accent flex items-center gap-3 rounded-sm px-2 py-1.5 text-sm outline-none"
                               @keydown.down.prevent="$el.closest('li').nextElementSibling?.querySelector('a')?.focus()"
                               @keydown.up.prevent="($el.closest('li').previousElementSibling?.querySelector('a') ?? $refs.input).focus()">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $result['title'] }}</span>
                                    <span class="text-muted-foreground block truncate text-xs">{{ $result['path'] }}</span>
                                </span>
                                @if ($result['isUnpublished'])
                                    <x-lynx-admin::ui.badge variant="outline">Unpublished</x-lynx-admin::ui.badge>
                                @endif
                                <span class="text-muted-foreground shrink-0 text-xs">{{ $result['template'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="text-muted-foreground border-t px-2 pt-1.5 pb-1 text-xs">
                    {{ count($results) }} of {{ $total }} {{ $total === 1 ? 'page' : 'pages' }}.
                    @unless ($isSelector) Searching titles and searchable fields; a selector such as <code>template=course</code> searches anything. @endunless
                </p>
            @endif
        </div>
    @endif
</div>
