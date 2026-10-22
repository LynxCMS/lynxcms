<x-lynx-admin::layout title="Roles">
    <x-lynx-admin::ui.page-header title="Roles" description="What each role may do, on which templates and branches." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        <x-slot:actions>
            <x-lynx-admin::ui.button :href="route('lynx.admin.roles.create')"><x-lucide-plus aria-hidden="true" /> New role</x-lynx-admin::ui.button>
        </x-slot:actions>
    </x-lynx-admin::ui.page-header>
    <livewire:lynx-admin.role-list />
</x-lynx-admin::layout>
