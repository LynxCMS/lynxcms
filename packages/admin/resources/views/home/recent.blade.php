{{-- Lynx's home panel of the pages changed last (HomeDefaults, key "recent"). --}}
@php($recent = app(\LynxCms\Admin\Home\HomeOverview::class)->recent())
<x-lynx-admin::home.section id="home-recent" data-home-panel="recent" icon="history"
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
