<x-lynx-admin::layout title="Site texts">
    <x-lynx-admin::ui.page-header title="Site texts" description="Texts the templates share on every page, such as the footer." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.page-strings :page-id="(int) lynx()->pages->get('/')?->id" scope="site" />
</x-lynx-admin::layout>
