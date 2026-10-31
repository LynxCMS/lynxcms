{{--
    The panel beside the editor's fields (EditorPanel): whether the page is live and has a draft,
    how much of it each language has its own text for, and its latest activity. Templates that lay
    out their own sidebar keep theirs instead (ADR-0063).
--}}
@php($card = 'bg-card text-card-foreground grid gap-3 rounded-xl border p-4 shadow-sm')
<aside class="grid content-start gap-4 lg:sticky lg:top-20" aria-label="{{ __('lynx-admin::editor.panel.about') }}" data-editor-panel>
    <section class="{{ $card }}" aria-labelledby="panel-publishing">
        <h2 id="panel-publishing" class="text-sm font-semibold">{{ __('lynx-admin::editor.panel.publishing') }}</h2>
        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm">
            <dt class="text-muted-foreground">{{ __('lynx-admin::editor.panel.live') }}</dt>
            <dd>{{ __($panel['isPublished'] ? 'lynx-admin::editor.panel.published' : 'lynx-admin::editor.panel.not_published') }}</dd>
            <dt class="text-muted-foreground">{{ __('lynx-admin::editor.panel.draft') }}</dt>
            <dd>{{ __($panel['hasDraft'] ? 'lynx-admin::editor.panel.draft_waiting' : 'lynx-admin::editor.panel.none') }}</dd>
        </dl>
        @if ($panel['hasDraft'] && ! $isLocked && $canEdit)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="review">{{ __('lynx-admin::editor.review_changes') }}</x-lynx-admin::ui.button>
        @endif
    </section>

    @if ($panel['languages'] !== [])
        <section class="{{ $card }}" aria-labelledby="panel-languages">
            <h2 id="panel-languages" class="text-sm font-semibold">{{ __('lynx-admin::editor.panel.languages') }}</h2>
            <ul class="grid gap-2 text-sm">
                @foreach ($panel['languages'] as $language)
                    <li class="grid grid-cols-[minmax(0,6rem)_minmax(0,1fr)_auto] items-center gap-3">
                        <span class="truncate">{{ $language['name'] }}</span>
                        <span class="bg-muted h-1.5 overflow-hidden rounded-full" aria-hidden="true">
                            <span class="bg-primary block h-full" style="width: {{ $language['total'] > 0 ? round($language['own'] / $language['total'] * 100) : 0 }}%"></span>
                        </span>
                        <span class="text-muted-foreground tabular-nums">{{ __('lynx-admin::editor.panel.own_count', ['own' => $language['own'], 'total' => $language['total']]) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="lynx-hint text-muted-foreground text-sm">{{ __('lynx-admin::editor.panel.own_help') }}</p>
        </section>
    @endif

    @if ($panel['activity'] !== [])
        <section class="{{ $card }}" aria-labelledby="panel-activity">
            <h2 id="panel-activity" class="text-sm font-semibold">{{ __('lynx-admin::editor.panel.activity') }}</h2>
            <ol class="grid gap-2.5 text-sm">
                @foreach ($panel['activity'] as $entry)
                    <li class="grid">
                        <x-lynx-admin::activity-summary :what="$entry['what']" />
                        <span class="text-muted-foreground text-xs" title="{{ $entry['at'] }}">{{ $entry['who'] }}, {{ $entry['when'] }}</span>
                    </li>
                @endforeach
            </ol>
            <button type="button" class="justify-self-start text-sm underline underline-offset-4 hover:text-primary" wire:click="$set('tab', 'settings')">{{ __('lynx-admin::editor.panel.all_activity') }}</button>
        </section>
    @endif
</aside>
