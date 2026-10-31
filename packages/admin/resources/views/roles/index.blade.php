<x-lynx-admin::layout :title="__('lynx-admin::roles.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::roles.title')" :description="__('lynx-admin::roles.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        <x-slot:actions>
            <x-lynx-admin::ui.button :href="route('lynx.admin.roles.create')"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::roles.new') }}</x-lynx-admin::ui.button>
        </x-slot:actions>
    </x-lynx-admin::ui.page-header>
    <livewire:lynx-admin.role-list />
</x-lynx-admin::layout>
