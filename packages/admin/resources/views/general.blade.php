<x-lynx-admin::layout :title="__('lynx-admin::general.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::general.title')" :description="__('lynx-admin::general.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <div class="grid gap-6">
        <livewire:lynx-admin.branding-form />
        <livewire:lynx-admin.not-found-page-form />
    </div>
</x-lynx-admin::layout>
