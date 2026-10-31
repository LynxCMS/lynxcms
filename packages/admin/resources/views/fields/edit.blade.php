<x-lynx-admin::layout :title="$field === null ? __('lynx-admin::fields.new') : __('lynx-admin::fields.heading', ['name' => $field])" :crumbs="[__('lynx-admin::fields.title') => route('lynx.admin.fields')]">
    {{-- The heading is the form's own: it carries Save, and the field's label as it is typed. --}}
    <livewire:lynx-admin.field-form :field="$field" />
</x-lynx-admin::layout>
