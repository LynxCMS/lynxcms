<x-lynx-admin::layout :title="__('lynx-seo::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-seo::admin.title')" :description="__('lynx-seo::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-seo::admin.tabs')
    <livewire:lynx-seo.templates />
</x-lynx-admin::layout>
