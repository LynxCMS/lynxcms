{{--
    The admin's home screen (HomeController): a welcome with today's date and common actions, the
    row of stats, then two columns. The wide column holds what waits for someone: pages
    with a draft, the next scheduled changes, then the panels modules add (HomeScreen). The narrow
    one is the record of what changed last. On small screens the work comes first.
--}}
@php
    $andList = fn (array $items): string => count($items) < 2 ? implode('', $items) : implode(', ', array_slice($items, 0, -1)).' '.__('lynx-admin::activity.and').' '.last($items);
    $seeAll = fn (int $total, string $filter): array => $total > \LynxCms\Admin\Home\HomeOverview::LIMIT
        ? ['url' => route('lynx.admin.pages', ['filter' => $filter]), 'label' => __('lynx-admin::home.see_all', ['count' => $total])]
        : ['url' => null, 'label' => null];
    $scheduleActions = ['publish', 'unpublish', 'publish_draft'];
@endphp
<x-lynx-admin::layout :title="__('lynx-admin::home.title')">
    <x-lynx-admin::ui.page-header :title="$name === null ? __('lynx-admin::home.welcome_plain') : __('lynx-admin::home.welcome', ['name' => $name])"
        :description="__('lynx-admin::home.today', ['date' => $today])" class="mb-6 [&_h1]:text-primary dark:[&_h1]:text-[color-mix(in_oklab,var(--primary),var(--foreground)_30%)] [&_h1]:text-2xl [&_h1]:font-semibold" data-home-welcome>
        @if ($actions !== [])
            <x-slot:actions>
                <nav class="flex flex-wrap gap-2" aria-label="{{ __('lynx-admin::home.actions.label') }}">
                    @foreach ($actions as $action)
                        <x-lynx-admin::ui.button :href="$action['url']" :variant="$loop->first ? 'default' : 'outline'" data-home-action="{{ $action['key'] }}">
                            @if ($action['icon'] !== null)
                                <x-lynx-admin::ui.icon :name="$action['icon']" aria-hidden="true" />
                            @endif
                            {{ $action['label'] }}
                        </x-lynx-admin::ui.button>
                    @endforeach
                </nav>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>

    <x-lynx-admin::home.stats :stats="$stats" class="mb-10" />

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-12">
        <div class="grid min-w-0 content-start gap-10">
            <x-lynx-admin::home.section id="home-drafts" data-home-panel="drafts" icon="file-pen" :href="route('lynx.admin.pages', ['filter' => 'drafts'])"
                :title="__('lynx-admin::home.drafts.title')" :count="$drafts['total']"
                :see-all-url="$seeAll($drafts['total'], 'drafts')['url']" :see-all-label="$seeAll($drafts['total'], 'drafts')['label']"
                :is-empty="$drafts['pages'] === []" empty-icon="file-pen"
                :empty-title="__('lynx-admin::home.drafts.empty')" :empty-help="__('lynx-admin::home.drafts.empty_help')">
                @foreach ($drafts['pages'] as $page)
                    <x-lynx-admin::home.row :url="$page['url']" :title="$page['title']" data-home-page="{{ $page['id'] }}">
                        @if ($page['date'] !== null)
                            <x-slot:aside><span title="{{ $page['changedAt'] }}">{{ $page['date'] }}</span></x-slot:aside>
                        @endif
                    </x-lynx-admin::home.row>
                @endforeach
            </x-lynx-admin::home.section>

            <x-lynx-admin::home.section id="home-upcoming" data-home-panel="upcoming" icon="calendar-clock" :href="route('lynx.admin.pages', ['filter' => 'scheduled'])"
                :title="__('lynx-admin::home.upcoming.title')" :count="$upcoming['total']"
                :see-all-url="$seeAll($upcoming['total'], 'scheduled')['url']" :see-all-label="$seeAll($upcoming['total'], 'scheduled')['label']"
                :is-empty="$upcoming['entries'] === []" empty-icon="calendar-clock"
                :empty-title="__('lynx-admin::home.upcoming.empty')" :empty-help="__('lynx-admin::home.upcoming.empty_help')">
                @foreach ($upcoming['entries'] as $entry)
                    <x-lynx-admin::home.row :url="$entry['url']" :title="$entry['title']" data-home-page="{{ $entry['id'] }}">
                        {{ in_array($entry['action'], $scheduleActions, true) ? __('lynx-admin::home.upcoming.'.$entry['action']) : $entry['action'] }}
                        @if ($entry['languages'] !== [])
                            {{ __('lynx-admin::home.upcoming.in', ['language' => $andList($entry['languages'])]) }}
                        @endif
                        @if ($entry['failed'])
                            <x-lynx-admin::ui.badge tone="danger" class="ms-1 align-middle" data-home-failed>{{ __('lynx-admin::home.upcoming.failed') }}</x-lynx-admin::ui.badge>
                        @endif
                        <x-slot:aside><time datetime="{{ $entry['iso'] }}" title="{{ $entry['at'] }}">{{ $entry['date'] }}</time></x-slot:aside>
                        <x-slot:aside-below>{{ $entry['when'] }}</x-slot:aside-below>
                    </x-lynx-admin::home.row>
                @endforeach
            </x-lynx-admin::home.section>

            @foreach ($panels as $panel)
                @include($panel)
            @endforeach
        </div>

        <x-lynx-admin::home.section id="home-recent" data-home-panel="recent" icon="history" class="lg:border-s lg:ps-12"
            :title="__('lynx-admin::home.recent.title')"
            :is-empty="$recent === []" empty-icon="history"
            :empty-title="__('lynx-admin::home.recent.empty')" :empty-help="__('lynx-admin::home.recent.empty_help')">
            @foreach ($recent as $entry)
                <x-lynx-admin::home.row :url="$entry['url']" :title="$entry['title']" data-home-page="{{ $entry['id'] }}" class="grid-cols-1">
                    <span class="text-foreground block"><x-lynx-admin::activity-summary :what="$entry['what']" /></span>
                    <span class="block text-xs" title="{{ $entry['at'] }}">{{ $entry['who'] }} · {{ $entry['when'] }}</span>
                </x-lynx-admin::home.row>
            @endforeach
        </x-lynx-admin::home.section>
    </div>
</x-lynx-admin::layout>
