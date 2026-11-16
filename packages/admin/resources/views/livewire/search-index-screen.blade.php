{{-- The Search index screen (ADR-0120): an overview of what the index holds, and tools to rebuild it and test a page's indexed words. --}}
<x-lynx-admin::ui.tabs value="overview" class="gap-6">
    <x-lynx-admin::ui.tabs-list variant="underline">
        <x-lynx-admin::ui.tabs-trigger value="overview">{{ __('lynx-admin::search_index.tabs.overview') }}</x-lynx-admin::ui.tabs-trigger>
        <x-lynx-admin::ui.tabs-trigger value="tools">{{ __('lynx-admin::search_index.tabs.tools') }}</x-lynx-admin::ui.tabs-trigger>
    </x-lynx-admin::ui.tabs-list>

    <x-lynx-admin::ui.tabs-content value="overview" class="grid gap-6" data-tab="overview">
        @include('lynx-admin::livewire.partials.search-index-overview')
    </x-lynx-admin::ui.tabs-content>

    <x-lynx-admin::ui.tabs-content value="tools" class="grid gap-6" data-tab="tools">
        @include('lynx-admin::livewire.partials.search-index-tools')
    </x-lynx-admin::ui.tabs-content>
</x-lynx-admin::ui.tabs>
