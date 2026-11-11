{{-- One group of conditions: whether a page must match all of them or any, and the conditions. --}}
<fieldset class="bg-card text-card-foreground grid min-w-0 grid-cols-1 gap-4 rounded-xl border p-4 shadow-xs sm:p-5" wire:key="finder-group-{{ $g }}" data-finder-group="{{ $g }}">
    <legend class="sr-only">{{ __('lynx-finder::admin.group.heading', ['number' => $g + 1]) }}</legend>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm font-semibold" aria-hidden="true">{{ __('lynx-finder::admin.group.heading', ['number' => $g + 1]) }}</span>
        <select class="blat-select w-auto" wire:model.live="search.groups.{{ $g }}.match" aria-label="{{ __('lynx-finder::admin.group.match', ['number' => $g + 1]) }}" data-finder-group-match>
            <option value="all">{{ __('lynx-finder::admin.group.all') }}</option>
            <option value="any">{{ __('lynx-finder::admin.group.any') }}</option>
        </select>
        @if ($removable)
            <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" class="ms-auto" wire:click="removeGroup({{ $g }})" data-finder-remove-group>
                <x-lucide-trash-2 aria-hidden="true" /> {{ __('lynx-finder::admin.group.remove') }}
            </x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($group['conditions'] === [])
        <p class="text-muted-foreground text-sm">{{ __('lynx-finder::admin.group.empty') }}</p>
    @else
        <ul class="grid gap-3">
            @foreach ($group['conditions'] as $c => $condition)
                @include('lynx-finder::admin.partials.condition', ['g' => $g, 'c' => $c, 'condition' => $condition])
            @endforeach
        </ul>
    @endif

    <div>
        <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" wire:click="addCondition({{ $g }})" data-finder-add-condition>
            <x-lucide-plus aria-hidden="true" /> {{ __('lynx-finder::admin.group.add_condition') }}
        </x-lynx-admin::ui.button>
    </div>
</fieldset>
