{{-- Settings > Page references (ADR-0145): reports on the links between pages, and the index's health. --}}
<x-lynx-admin::layout :title="__('lynx-admin::page_references.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::page_references.title')" :description="__('lynx-admin::page_references.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.page-references />
</x-lynx-admin::layout>
