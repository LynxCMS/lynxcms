<x-lynx-admin::layout :title="$field === null ? 'New field' : 'Field '.$field" :crumbs="['Fields' => route('lynx.admin.fields')]">
    <x-lynx-admin::ui.page-header :title="$field === null ? 'New field' : $field" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.field-form :field="$field" />
</x-lynx-admin::layout>
