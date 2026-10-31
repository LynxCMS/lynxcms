<x-lynx-admin::layout :title="__('lynx-admin::pages.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::pages.title')" :description="__('lynx-admin::pages.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.page-tree />
</x-lynx-admin::layout>
