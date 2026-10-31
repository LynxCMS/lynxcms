{{-- Create field, opened from any list of the builder: fields.create runs at once and the new field joins that list. --}}
<x-lynx-admin::ui.dialog id="new-subfield">
    <x-lynx-admin::ui.dialog-content class="sm:max-w-md" :close-on-overlay="false">
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>{{ __('lynx-admin::fields.create.title') }}</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>{{ __('lynx-admin::fields.create.description') }}</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>
        <div class="grid gap-4" x-on:keydown.enter.prevent="$wire.createSubfield()">
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-name" class="font-medium">{{ __('lynx-admin::fields.create.name') }}</label>
                <input id="new-subfield-name" type="text" class="blat-input font-mono" wire:model="newSubfield.name" autocomplete="off" @error('newSubfield.name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground">{!! __('lynx-admin::fields.create.name_help', ['example' => '<code>caption</code>', 'code' => '<code>$block->caption</code>']) !!}</p>
                @error('newSubfield.name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-type" class="font-medium">{{ __('lynx-admin::fields.create.holds') }}</label>
                <select id="new-subfield-type" class="blat-select" wire:model.live="newSubfield.type">
                    @foreach ($subfieldTypes as $typeName => $typeInfo)
                        <option value="{{ $typeName }}">{{ app(\LynxCms\Admin\Editor\FieldKinds::class)->for($typeName)['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-muted-foreground">{{ app(\LynxCms\Admin\Editor\FieldKinds::class)->for($newSubfield['type'])['description'] }}</p>
                @error('newSubfield.type') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1 text-sm">
                <label for="new-subfield-label" class="font-medium">{{ __('lynx-admin::fields.create.label') }}</label>
                <x-lynx-admin::localized-input id="new-subfield-label" model="newSubfield.label" :placeholder="__('lynx-admin::fields.create.label_placeholder')" />
                @error('newSubfield.label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            @if (lynx()->languages->isMultilingual() && ($types[$newSubfield['type']]['translatable'] ?? false))
                <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model="newSubfield.translatable"> {{ __('lynx-admin::fields.create.translatable') }}</label>
            @endif
            <p class="text-muted-foreground text-xs">{{ __('lynx-admin::fields.create.more') }}</p>
        </div>
        <x-lynx-admin::ui.dialog-footer>
            <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="open = false">{{ __('lynx-admin::fields.create.cancel') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button type="button" wire:click="createSubfield">{{ __('lynx-admin::fields.create.submit') }}</x-lynx-admin::ui.button>
        </x-lynx-admin::ui.dialog-footer>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
