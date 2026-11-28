<x-lynx-admin::layout :title="__('lynx-backup::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-backup::admin.title')" :description="__('lynx-backup::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-backup.screen />
</x-lynx-admin::layout>
