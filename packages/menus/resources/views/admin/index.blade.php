<x-lynx-admin::layout :title="__('lynx-menus::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-menus::admin.title')" :description="__(lynx()->languages->isMultilingual() ? 'lynx-menus::admin.description_languages' : 'lynx-menus::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        <x-slot:actions>
            <x-lynx-admin::ui.button type="button" x-on:click="$dispatch('open-dialog-lynx-new-menu')" data-new-menu><x-lucide-plus aria-hidden="true" /> {{ __('lynx-menus::admin.new') }}</x-lynx-admin::ui.button>
        </x-slot:actions>
    </x-lynx-admin::ui.page-header>
    <livewire:lynx-menus.list />
</x-lynx-admin::layout>
