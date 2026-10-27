<x-lynx-admin::layout :title="$field === null ? 'New field' : 'Field '.$field" :crumbs="['Fields' => route('lynx.admin.fields')]">
    {{-- The heading is the form's own: it carries Save, and the field's label as it is typed. --}}
    <livewire:lynx-admin.field-form :field="$field" />
</x-lynx-admin::layout>
