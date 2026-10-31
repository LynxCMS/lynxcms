<x-lynx-admin::layout :title="$role === null ? __('lynx-admin::roles.new') : __('lynx-admin::roles.heading', ['name' => $role])" :crumbs="[__('lynx-admin::roles.title') => route('lynx.admin.roles')]">
    <x-lynx-admin::ui.page-header :title="$role === null ? __('lynx-admin::roles.new') : $role" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.role-form :role="$role" />
</x-lynx-admin::layout>
