<x-lynx-admin::layout :title="__('lynx-insights::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-insights::admin.title')" :description="__('lynx-insights::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-insights.screen />
</x-lynx-admin::layout>
