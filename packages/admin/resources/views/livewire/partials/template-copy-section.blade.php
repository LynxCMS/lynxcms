{{-- Copy to templates: a section, as last saved, into the ticked templates (templates.copy-section). --}}
<x-lynx-admin::ui.dialog id="copy-section">
    <x-lynx-admin::ui.dialog-content class="sm:max-w-lg" :close-on-overlay="false">
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>{{ __('lynx-admin::templates.copy.title') }}</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>{{ __('lynx-admin::templates.copy.description') }}</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>

        @if (! $isCopySectionSaved)
            <p class="text-sm" role="status">{{ __('lynx-admin::templates.copy.save_first') }}</p>
        @else
            <div class="grid gap-4">
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">{{ __('lynx-admin::templates.copy.to') }}</legend>
                    <div class="grid max-h-64 gap-2 overflow-y-auto pe-1">
                    @forelse ($copyTargets as $targetName => $target)
                        <label class="flex items-start gap-2 text-sm" wire:key="copy-target-{{ $targetName }}">
                            <input class="blat-checkbox mt-0.5" type="checkbox" value="{{ $targetName }}" wire:model="copyTo" @disabled($target['problem'] !== null)>
                            <span class="grid gap-0.5">
                                <span @class(['text-muted-foreground' => $target['problem'] !== null])>{{ $target['label'] }} <code class="text-muted-foreground text-xs">{{ $targetName }}</code></span>
                                @if ($target['problem'] !== null)
                                    <span class="text-muted-foreground text-xs">{{ $target['problem'] }}</span>
                                @elseif ($renameConflicts && $target['renames'] !== [])
                                    <span class="text-muted-foreground text-xs">{{ __('lynx-admin::templates.copy.renames', ['fields' => collect($target['renames'])->map(fn (string $new, string $old): string => $old.' → '.$new)->implode(', ')]) }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::templates.copy.none') }}</p>
                    @endforelse
                    </div>
                </fieldset>

                <div class="grid gap-2 rounded-md border p-3">
                    <label class="flex items-center gap-2 text-sm font-medium"><input class="blat-checkbox" type="checkbox" wire:model.live="renameConflicts"> {{ __('lynx-admin::templates.copy.rename') }}</label>
                    <p class="text-muted-foreground text-xs">{{ __('lynx-admin::templates.copy.rename_help') }}</p>
                    @if ($renameConflicts)
                        <div class="grid gap-1 text-sm">
                            <label for="copy-section-prefix" class="font-medium">{{ __('lynx-admin::templates.copy.prefix') }}</label>
                            <input id="copy-section-prefix" type="text" class="blat-input w-48 font-mono" wire:model.live.debounce.400ms="copyPrefix" autocomplete="off" @error('prefix') aria-invalid="true" @enderror>
                            @error('prefix') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                @error('to') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                @error('section') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
        @endif

        <x-lynx-admin::ui.dialog-footer>
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="closeCopySection">{{ __('lynx-admin::templates.copy.cancel') }}</x-lynx-admin::ui.button>
            @if ($isCopySectionSaved)
                <x-lynx-admin::ui.button type="button" wire:click="copySectionTo">{{ __('lynx-admin::templates.copy.submit') }}</x-lynx-admin::ui.button>
            @endif
        </x-lynx-admin::ui.dialog-footer>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
