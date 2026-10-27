{{--
    The field form's builder for repeaters and matrices (EditsItemSettings): a matrix lists block
    types, each with its own fields; a repeater lists the fields every item has. Fields must exist
    to be listed, so each list can also create one on the spot.
--}}
@php($isMatrix = $type === 'matrix')
<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="field-items">
    <div class="grid gap-1">
        <h2 id="field-items" class="text-base font-semibold">{{ $isMatrix ? 'Block types' : 'Fields of each item' }}</h2>
        <p class="text-muted-foreground text-sm">
            @if ($isMatrix)
                Editors build the page from blocks, in any order. Each block type, such as Text, Image or Quote, shows its own fields.
            @else
                Editors add as many items as they need, and every item shows these fields.
            @endif
            A field listed here is a field of the site: pick one, or create a new one.
        </p>
    </div>
    @error($isMatrix ? 'settings.types' : 'settings.fields') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror

    @if ($isMatrix)
        @include('lynx-admin::livewire.partials.items.types')
        @if ($itemTypes !== [])
            @include('lynx-admin::livewire.partials.items.add-buttons')
        @endif
    @else
        <x-lynx-admin::builder-panel title="Fields" icon="list" description="Each item shows these fields, in this order.">
            @include('lynx-admin::livewire.partials.items.subfields', ['list' => 'fields', 'model' => 'itemFields', 'rows' => $itemFields])
        </x-lynx-admin::builder-panel>
        <x-lynx-admin::builder-panel title="In the page editor" icon="eye" description="How an item looks to editors.">
            <div class="max-w-xl">@include('lynx-admin::livewire.partials.items.block-mock', ['title' => 'Item', 'rows' => $itemFields])</div>
        </x-lynx-admin::builder-panel>
    @endif

    @if ($canEdit)
        @include('lynx-admin::livewire.partials.items.create-dialog')
    @endif
</section>
