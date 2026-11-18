{{-- The inputs of some of an item's subfields (inputs/items.blade.php), in its 12 column grid. --}}
@foreach ($subfields as $subfield)
    <x-lynx-admin::editor.subfield
        :field="$field->name"
        :index="$index"
        :subfield="$subfield"
        :model="$model.'.'.$index.'.values.'.$subfield->name()"
        :values="(array) ($item['values'][$subfield->name()] ?? [])"
        :disabled="$disabled"
        :page-id="$field->pageId"
    />
@endforeach
