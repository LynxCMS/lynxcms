{{-- Lynx's home panel of pages with a draft (HomeDefaults, key "drafts"). --}}
@php
    $drafts = app(\LynxCms\Admin\Home\HomeOverview::class)->drafts();
    $hasMore = $drafts['total'] > \LynxCms\Admin\Home\HomeOverview::LIMIT;
@endphp
<x-lynx-admin::home.section id="home-drafts" data-home-panel="drafts" icon="file-pen" :href="route('lynx.admin.pages', ['filter' => 'drafts'])"
    :title="__('lynx-admin::home.drafts.title')" :count="$drafts['total']"
    :see-all-url="$hasMore ? route('lynx.admin.pages', ['filter' => 'drafts']) : null"
    :see-all-label="$hasMore ? __('lynx-admin::home.see_all', ['count' => $drafts['total']]) : null"
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
