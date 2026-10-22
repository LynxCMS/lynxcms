<x-lynx-admin::layout title="Page cache">
    <x-lynx-admin::ui.page-header title="Page cache" description="Pages guests see, stored as HTML and sent again until content changes." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-cache.page-cache />
    <div class="mt-6"><livewire:lynx-cache.fragments /></div>
</x-lynx-admin::layout>
