{{-- Duplicate a field (fields.duplicate): its new name and label; the new field then opens on its own screen. --}}
<x-lynx-admin::ui.dialog id="duplicate-field">
    <x-lynx-admin::ui.dialog-content class="sm:max-w-md" :close-on-overlay="false">
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>{{ __('lynx-admin::fields.duplicate.heading', ['field' => $duplicating]) }}</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>{{ __('lynx-admin::fields.duplicate.description') }}</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>

        <form wire:submit="duplicate" class="grid gap-4">
            <div class="grid gap-1 text-sm">
                <label for="duplicate-field-name" class="font-medium">{{ __('lynx-admin::fields.duplicate.name') }}</label>
                <input id="duplicate-field-name" type="text" class="blat-input font-mono" wire:model="duplicateName" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                @error('name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1 text-sm">
                <label for="duplicate-field-label" class="font-medium">{{ __('lynx-admin::fields.duplicate.label') }}</label>
                <input id="duplicate-field-label" type="text" class="blat-input" wire:model="duplicateLabel" autocomplete="off">
                <p class="text-muted-foreground text-xs">{{ __('lynx-admin::fields.duplicate.label_hint') }}</p>
                @error('label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <x-lynx-admin::ui.dialog-footer>
                <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="$dispatch('close-dialog-duplicate-field')">{{ __('lynx-admin::fields.duplicate.cancel') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button type="submit">{{ __('lynx-admin::fields.duplicate.submit') }}</x-lynx-admin::ui.button>
            </x-lynx-admin::ui.dialog-footer>
        </form>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
