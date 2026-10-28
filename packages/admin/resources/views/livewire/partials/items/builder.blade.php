{{--
    The builder tab for repeaters and matrices (EditsItemSettings): a matrix's block types as an
    accordion, each opening to its fields; a repeater's fields directly. Fields must exist to be
    listed, so each list can also create one on the spot. How it looks is on the Preview tab.
--}}
<div class="grid max-w-4xl gap-3">
    @error($type === 'matrix' ? 'settings.types' : 'settings.fields') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @if ($type === 'matrix')
        @include('lynx-admin::livewire.partials.items.types')
    @else
        @include('lynx-admin::livewire.partials.items.subfields', ['list' => 'fields', 'model' => 'itemFields', 'rows' => $itemFields, 'flush' => false])
        <div class="mt-3 grid gap-1.5">
            <label for="setting-item-header" class="text-sm font-medium">Header of a closed item</label>
            <input id="setting-item-header" type="text" class="blat-input max-w-md font-mono" wire:model="settings.item_label" autocomplete="off"
                   placeholder="{{ isset($itemFields[0]) ? '{'.$itemFields[0]['name'].'}' : '{field}' }}">
            <p class="text-muted-foreground text-sm">Field names in braces show their values, so editors find an item without opening it.</p>
            @error('settings.item_label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
