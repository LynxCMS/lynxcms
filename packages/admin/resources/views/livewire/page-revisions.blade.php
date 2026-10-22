<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-revisions">
    <h2 id="settings-revisions" class="mb-1 text-base font-semibold">Revisions</h2>
    <p class="mb-4 text-sm text-muted-foreground">The page's values after each save, newest first. Compare one with the page, or restore it: the current values are kept as a revision too.</p>


    @forelse ($revisions as $revision)
        @if ($loop->first) <ul class="divide-y divide-border text-sm"> @endif
        <li class="py-2" wire:key="revision-{{ $revision['id'] }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>
                    <span class="font-medium">Revision {{ $revision['revision'] }}</span>
                    <span class="text-muted-foreground">· {{ $revision['by'] }} · <time datetime="{{ $revision['created_at']->toIso8601String() }}">{{ $revision['created_at']->diffForHumans() }}</time></span>
                    @if ($loop->first) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">current</x-lynx-admin::ui.badge> @endif
                </span>
                @unless ($loop->first)
                    <span class="flex items-center gap-1" x-data="{ confirming: false }">
                        <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="compare({{ $revision['id'] }})">{{ $comparing === $revision['id'] ? 'Hide' : 'Compare' }}</x-lynx-admin::ui.button>
                        @if ($canRestore)
                            <x-lynx-admin::ui.button variant="ghost" size="xs" x-show="! confirming" x-on:click="confirming = true">Restore</x-lynx-admin::ui.button>
                            <span x-show="confirming" x-cloak class="flex items-center gap-1">
                                <span class="text-muted-foreground">Restore these values?</span>
                                <x-lynx-admin::ui.button size="xs" wire:click="restore({{ $revision['id'] }})">Yes, restore</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button variant="ghost" size="xs" x-on:click="confirming = false">Cancel</x-lynx-admin::ui.button>
                            </span>
                        @endif
                    </span>
                @endunless
            </div>
            @if ($comparing === $revision['id'])
                <div class="mt-3">
                    @if ($review === [])
                        <p class="text-muted-foreground">Same values as the page now.</p>
                    @else
                        <x-lynx-admin::editor.review :entries="$review" />
                    @endif
                </div>
            @endif
        </li>
        @if ($loop->last) </ul> @endif
    @empty
        <p class="text-sm text-muted-foreground">No revisions yet: they are made when the page's values are saved.</p>
    @endforelse
</section>
