<x-lynx-admin::layout :title="__('lynx-page-password::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-page-password::admin.title')" :description="__('lynx-page-password::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-page-password.settings />
</x-lynx-admin::layout>
