{{-- Saved searches (ManagesSavedSearches): open one, save the current search, delete the open one, and the CSV export. --}}
<div class="grid gap-3" data-finder-saved>
    <div class="flex flex-wrap items-center gap-3">
        @if ($saved !== [])
            <select class="blat-select w-auto max-w-full" aria-label="{{ __('lynx-finder::admin.saved.label') }}" x-on:change="$event.target.value && $wire.openSearch($event.target.value)" data-finder-open>
                <option value="">{{ __('lynx-finder::admin.saved.open') }}</option>
                @foreach ($saved as $item)
                    <option value="{{ $item['id'] }}" @selected($item['id'] === $savedSearch)>{{ $item['name'] }}{{ $item['shared'] ? ' ('.__('lynx-finder::admin.saved.shared').')' : '' }}</option>
                @endforeach
            </select>
        @endif
        @if ($savedSearch !== null && $canChangeSaved)
            <button type="button" wire:click="confirmDeleteSearch" class="text-destructive text-sm font-medium underline-offset-4 hover:underline" data-finder-delete-saved>{{ __('lynx-finder::admin.saved.delete') }}</button>
        @endif
        <span class="ms-auto flex flex-wrap gap-2">
            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" x-on:click="$dispatch('open-dialog-lynx-finder-save')" data-finder-save>
                <x-lucide-bookmark aria-hidden="true" /> {{ __('lynx-finder::admin.saved.save') }}
            </x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" size="sm" :href="$exportUrl" data-finder-export>
                <x-lucide-download aria-hidden="true" /> {{ __('lynx-finder::admin.export') }}
            </x-lynx-admin::ui.button>
        </span>
    </div>

    @if ($confirmingDelete)
        <div class="border-destructive/40 bg-destructive/5 flex flex-wrap items-center gap-3 rounded-md border px-4 py-3 text-sm" role="alert" data-finder-confirm-delete>
            <span class="me-auto">{{ __('lynx-finder::admin.saved.confirm_delete', ['name' => $saveName]) }}</span>
            <x-lynx-admin::ui.button size="sm" variant="destructive" type="button" wire:click="deleteSearch">{{ __('lynx-finder::admin.saved.confirm') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="cancelDeleteSearch">{{ __('lynx-finder::admin.saved.cancel') }}</x-lynx-admin::ui.button>
        </div>
    @endif

    <x-lynx-admin::ui.dialog id="lynx-finder-save" :open="$errors->has('name')">
        <x-lynx-admin::ui.dialog-content class="sm:max-w-md">
            <form wire:submit="saveSearch" class="grid gap-5" aria-labelledby="finder-save-title">
                <x-lynx-admin::ui.dialog-header>
                    <x-lynx-admin::ui.dialog-title id="finder-save-title">{{ __('lynx-finder::admin.saved.title') }}</x-lynx-admin::ui.dialog-title>
                    <x-lynx-admin::ui.dialog-description>{{ __('lynx-finder::admin.saved.help') }}</x-lynx-admin::ui.dialog-description>
                </x-lynx-admin::ui.dialog-header>
                <div class="grid gap-2">
                    <label for="finder-save-name" class="blat-label">{{ __('lynx-finder::admin.saved.name') }}</label>
                    <input id="finder-save-name" type="text" class="blat-input" wire:model="saveName" placeholder="{{ __('lynx-finder::admin.saved.name_placeholder') }}" maxlength="100" @error('name') aria-invalid="true" @enderror>
                    @error('name') <p class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                @if ($canShare)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" class="blat-checkbox" wire:model="saveShared" data-finder-share>
                        {{ __('lynx-finder::admin.saved.share') }}
                    </label>
                @endif
                <x-lynx-admin::ui.dialog-footer>
                    <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="$dispatch('close-dialog-lynx-finder-save')">{{ __('lynx-finder::admin.saved.cancel') }}</x-lynx-admin::ui.button>
                    @if ($savedSearch !== null && $canChangeSaved)
                        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="saveSearch(true)">{{ __('lynx-finder::admin.saved.save_new') }}</x-lynx-admin::ui.button>
                    @endif
                    <x-lynx-admin::ui.button type="submit">{{ __('lynx-finder::admin.saved.submit') }}</x-lynx-admin::ui.button>
                </x-lynx-admin::ui.dialog-footer>
            </form>
        </x-lynx-admin::ui.dialog-content>
    </x-lynx-admin::ui.dialog>
</div>
