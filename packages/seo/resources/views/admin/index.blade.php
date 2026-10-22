<x-lynx-admin::layout title="SEO">
    <x-lynx-admin::ui.page-header title="SEO" description="How pages show in search results and when shared." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-seo::admin.tabs')
    <livewire:lynx-seo.templates />
</x-lynx-admin::layout>
