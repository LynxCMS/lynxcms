{{-- Settings > Search index (ADR-0120): what the search index holds, and rebuilding it. --}}
<x-lynx-admin::layout :title="__('lynx-admin::search_index.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::search_index.title')" :description="__('lynx-admin::search_index.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.search-index />
</x-lynx-admin::layout>
