<x-lynx-admin::layout title="General">
    <x-lynx-admin::ui.page-header title="General" description="How the admin introduces itself to everyone who signs in." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <div class="grid gap-6">
        <livewire:lynx-admin.branding-form />
        <livewire:lynx-admin.not-found-page-form />
    </div>
</x-lynx-admin::layout>
