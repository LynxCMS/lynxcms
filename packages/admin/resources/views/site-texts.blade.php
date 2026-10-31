<x-lynx-admin::layout :title="__('lynx-admin::pages.site_texts.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::pages.site_texts.title')" :description="__('lynx-admin::pages.site_texts.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.page-strings :page-id="(int) lynx()->pages->get('/')?->id" scope="site" />
</x-lynx-admin::layout>
