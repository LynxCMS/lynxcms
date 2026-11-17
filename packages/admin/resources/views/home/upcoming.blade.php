{{-- Lynx's home panel of the next scheduled changes (HomeDefaults, key "upcoming"). --}}
@php
    $upcoming = app(\LynxCms\Admin\Home\HomeOverview::class)->upcoming();
    $hasMore = $upcoming['total'] > \LynxCms\Admin\Home\HomeOverview::LIMIT;
    $scheduleActions = ['publish', 'unpublish', 'publish_draft'];
    $andList = fn (array $items): string => count($items) < 2 ? implode('', $items) : implode(', ', array_slice($items, 0, -1)).' '.__('lynx-admin::activity.and').' '.last($items);
@endphp
<x-lynx-admin::home.section id="home-upcoming" data-home-panel="upcoming" icon="calendar-clock" :href="route('lynx.admin.pages', ['filter' => 'scheduled'])"
    :title="__('lynx-admin::home.upcoming.title')" :count="$upcoming['total']"
    :see-all-url="$hasMore ? route('lynx.admin.pages', ['filter' => 'scheduled']) : null"
    :see-all-label="$hasMore ? __('lynx-admin::home.see_all', ['count' => $upcoming['total']]) : null"
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
