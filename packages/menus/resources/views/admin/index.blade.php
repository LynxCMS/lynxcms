<x-lynx-admin::layout :title="__('lynx-menus::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-menus::admin.title')" :description="__(lynx()->languages->isMultilingual() ? 'lynx-menus::admin.description_languages' : 'lynx-menus::admin.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-menus.editor />
</x-lynx-admin::layout>
