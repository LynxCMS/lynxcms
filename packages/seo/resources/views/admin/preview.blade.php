<x-lynx-admin::layout title="SEO preview">
    <x-lynx-admin::ui.page-header :title="'SEO preview: '.$page->title" description="The saved page as a search result and as a shared link. Search engines and networks may shorten or rewrite them." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <div class="mb-6 flex gap-2">
        <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.pages.edit', $page->id)">Edit page</x-lynx-admin::ui.button>
    </div>
    @include('lynx-seo::admin.partials.cards', ['seo' => $seo])
</x-lynx-admin::layout>
