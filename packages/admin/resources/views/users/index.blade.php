<x-lynx-admin::layout :title="__('lynx-admin::users.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::users.title')" :description="__('lynx-admin::users.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.user-roles />
</x-lynx-admin::layout>
