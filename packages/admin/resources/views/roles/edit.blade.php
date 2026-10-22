<x-lynx-admin::layout :title="$role === null ? 'New role' : 'Role '.$role" :crumbs="['Roles' => route('lynx.admin.roles')]">
    <x-lynx-admin::ui.page-header :title="$role === null ? 'New role' : $role" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.role-form :role="$role" />
</x-lynx-admin::layout>
