<x-lynx-admin::layout :title="__('lynx-finder::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-finder::admin.title')" :description="__('lynx-finder::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-finder.screen />
</x-lynx-admin::layout>
