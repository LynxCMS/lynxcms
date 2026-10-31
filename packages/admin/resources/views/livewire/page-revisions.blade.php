<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-revisions">
    <h2 id="settings-revisions" class="mb-1 text-base font-semibold">{{ __('lynx-admin::settings.revisions.title') }}</h2>
    <p class="mb-4 text-sm text-muted-foreground">{{ __('lynx-admin::settings.revisions.intro') }}</p>


    @forelse ($revisions as $revision)
        @if ($loop->first) <ul class="divide-y divide-border text-sm"> @endif
        <li class="py-2" wire:key="revision-{{ $revision['id'] }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>
                    <span class="font-medium">{{ __('lynx-admin::settings.revisions.revision', ['number' => $revision['revision']]) }}</span>
                    <span class="text-muted-foreground">· {{ $revision['by'] }} · <time datetime="{{ $revision['created_at']->toIso8601String() }}">{{ $revision['created_at']->diffForHumans() }}</time></span>
                    @if ($loop->first) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">{{ __('lynx-admin::settings.revisions.current') }}</x-lynx-admin::ui.badge> @endif
                </span>
                @unless ($loop->first)
                    <span class="flex items-center gap-1" x-data="{ confirming: false }">
                        <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="compare({{ $revision['id'] }})">{{ __($comparing === $revision['id'] ? 'lynx-admin::settings.revisions.hide' : 'lynx-admin::settings.revisions.compare') }}</x-lynx-admin::ui.button>
                        @if ($canRestore)
                            <x-lynx-admin::ui.button variant="ghost" size="xs" x-show="! confirming" x-on:click="confirming = true">{{ __('lynx-admin::settings.revisions.restore') }}</x-lynx-admin::ui.button>
                            <span x-show="confirming" x-cloak class="flex items-center gap-1">
                                <span class="text-muted-foreground">{{ __('lynx-admin::settings.revisions.confirm') }}</span>
                                <x-lynx-admin::ui.button size="xs" wire:click="restore({{ $revision['id'] }})">{{ __('lynx-admin::settings.revisions.yes_restore') }}</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button variant="ghost" size="xs" x-on:click="confirming = false">{{ __('lynx-admin::settings.revisions.cancel') }}</x-lynx-admin::ui.button>
                            </span>
                        @endif
                    </span>
                @endunless
            </div>
            @if ($comparing === $revision['id'])
                <div class="mt-3">
                    @if ($review === [])
                        <p class="text-muted-foreground">{{ __('lynx-admin::settings.revisions.same') }}</p>
                    @else
                        <x-lynx-admin::editor.review :entries="$review" />
                    @endif
                </div>
            @endif
        </li>
        @if ($loop->last) </ul> @endif
    @empty
        <p class="text-sm text-muted-foreground">{{ __('lynx-admin::settings.revisions.empty') }}</p>
    @endforelse
</section>
