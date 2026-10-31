<x-lynx-admin::layout :title="__('lynx-sitemap::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-sitemap::admin.title')" :description="__(lynx()->languages->isMultilingual() ? 'lynx-sitemap::admin.description_languages' : 'lynx-sitemap::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-sitemap.settings />
</x-lynx-admin::layout>
