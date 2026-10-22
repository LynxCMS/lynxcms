{{-- A page table (ADR-0058): pages made for this page, listed with the field's columns. --}}
<livewire:lynx-admin.page-table
    wire:model="{{ $model }}"
    :value="(array) ($value ?? [])"
    :page-id="$field->pageId"
    :settings="(array) $field->settings"
    :disabled="$disabled"
    :key="'page-table-'.$model"
/>
