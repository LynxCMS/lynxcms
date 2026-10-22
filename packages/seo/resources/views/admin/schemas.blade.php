<x-lynx-admin::layout title="Structured data">
    <x-lynx-admin::ui.page-header title="SEO" description="How pages show in search results and when shared." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-seo::admin.tabs')
    <livewire:lynx-seo.schemas />
</x-lynx-admin::layout>
