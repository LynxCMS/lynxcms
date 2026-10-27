{{-- Create field, opened from any list of the builder: fields.create runs at once and the new field joins that list. --}}
<x-lynx-admin::ui.dialog id="new-subfield">
    <x-lynx-admin::ui.dialog-content class="sm:max-w-md" :close-on-overlay="false">
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>Create a field</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>It is created now, and you can list it in other blocks and templates too.</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>
        <div class="grid gap-4" x-on:keydown.enter.prevent="$wire.createSubfield()">
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-name" class="font-medium">Name</label>
                <input id="new-subfield-name" type="text" class="blat-input font-mono" wire:model="newSubfield.name" autocomplete="off" @error('newSubfield.name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground">Lowercase letters, digits and underscores, such as <code>caption</code>. Views read it as <code>$block->caption</code>.</p>
                @error('newSubfield.name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-type" class="font-medium">What it holds</label>
                <select id="new-subfield-type" class="blat-select" wire:model.live="newSubfield.type">
                    @foreach ($subfieldTypes as $typeName => $typeInfo)
                        <option value="{{ $typeName }}">{{ app(\LynxCms\Admin\Editor\FieldKinds::class)->for($typeName)['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-muted-foreground">{{ app(\LynxCms\Admin\Editor\FieldKinds::class)->for($newSubfield['type'])['description'] }}</p>
                @error('newSubfield.type') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-label" class="font-medium">Label</label>
                <x-lynx-admin::localized-input id="new-subfield-label" model="newSubfield.label" placeholder="From the name" />
                @error('newSubfield.label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            @if (lynx()->languages->isMultilingual() && ($types[$newSubfield['type']]['translatable'] ?? false))
                <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model="newSubfield.translatable"> Translatable: one value per language</label>
            @endif
            <p class="text-muted-foreground text-xs">More settings, such as a rich text editor, are on the field's own screen after it is created.</p>
        </div>
        <x-lynx-admin::ui.dialog-footer>
            <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="open = false">Cancel</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button type="button" wire:click="createSubfield">Create and add</x-lynx-admin::ui.button>
        </x-lynx-admin::ui.dialog-footer>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
