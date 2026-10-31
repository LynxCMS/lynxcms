<x-lynx-admin::layout :title="__('lynx-seo::admin.preview.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-seo::admin.preview.heading', ['page' => $page->title])" :description="__('lynx-seo::admin.preview.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <div class="mb-6 flex gap-2">
        <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.pages.edit', $page->id)">{{ __('lynx-seo::admin.preview.edit') }}</x-lynx-admin::ui.button>
    </div>
    @include('lynx-seo::admin.partials.cards', ['seo' => $seo])
</x-lynx-admin::layout>
