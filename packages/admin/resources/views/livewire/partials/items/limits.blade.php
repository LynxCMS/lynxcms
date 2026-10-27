{{-- A repeater's or matrix's Limits and display tab (EditsItemDisplay): how many, how deep, the add button and how items start. --}}
@php($noun = $type === 'matrix' ? 'block' : 'item')
<div class="grid max-w-3xl gap-8" data-item-limits>
    <section class="grid gap-4" aria-labelledby="limits-count">
        <h2 id="limits-count" class="text-base font-semibold">How many</h2>
        <p class="flex flex-wrap items-center gap-2 text-sm">
            <label for="setting-limit-min">Pages have at least</label>
            <input id="setting-limit-min" type="number" min="0" class="blat-input w-20" wire:model="settings.min" placeholder="0" @error('settings.min') aria-invalid="true" @enderror>
            <label for="setting-limit-max">and at most</label>
            <input id="setting-limit-max" type="number" min="0" class="blat-input w-20" wire:model="settings.max" placeholder="any" @error('settings.max') aria-invalid="true" @enderror>
            <span>{{ Str::plural($noun) }}.</span>
        </p>
        @foreach (['settings.min', 'settings.max'] as $key) @error($key) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror @endforeach
        <p class="flex flex-wrap items-center gap-2 text-sm">
            <label for="setting-limit-depth">Editors can indent {{ $noun === 'item' ? 'an item' : 'a block' }}</label>
            <select id="setting-limit-depth" class="blat-select w-auto" wire:model="settings.max_depth">
                <option value="">not at all</option>
                @foreach (range(1, 5) as $depth)
                    <option value="{{ $depth }}">{{ $depth }} {{ Str::plural('level', $depth) }}</option>
                @endforeach
            </select>
            <span>under the one above it.</span>
        </p>
        @error('settings.max_depth') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    </section>

    <section class="grid gap-5 border-t pt-6" aria-labelledby="limits-display">
        <h2 id="limits-display" class="text-base font-semibold">In the page editor</h2>
        <div class="grid gap-1.5">
            <label for="setting-limit-add" class="text-sm font-medium">Text of the add button</label>
            <x-lynx-admin::localized-input id="setting-limit-add" model="addLabel" placeholder="Add" />
            @error('settings.add_label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        @if ($type === 'matrix')
            <div class="grid gap-1.5">
                <label for="setting-limit-header" class="text-sm font-medium">Header of a closed block, when its type has none</label>
                <input id="setting-limit-header" type="text" class="blat-input max-w-md font-mono" wire:model="settings.item_label" autocomplete="off">
                <p class="text-muted-foreground text-sm">Field names in braces show their values.</p>
            </div>
        @endif
        <div class="grid gap-1.5" role="radiogroup" aria-labelledby="limits-start">
            <span id="limits-start" class="text-sm font-medium">{{ ucfirst(Str::plural($noun)) }} start</span>
            @foreach (['' => ['Closed, except new ones', 'Editors see every header and open what they change.'], 'open' => ['Open', 'For a few short '.Str::plural($noun).'.'], 'collapsed' => ['Closed', 'For long pages.']] as $value => [$title, $hint])
                <label class="has-checked:border-primary has-checked:bg-primary/5 flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                    <input type="radio" class="mt-0.5 accent-primary" name="setting-collapse" value="{{ $value }}" wire:model="settings.collapse">
                    <span class="grid gap-0.5"><span class="font-medium">{{ $title }}</span><span class="text-muted-foreground">{{ $hint }}</span></span>
                </label>
            @endforeach
            @error('settings.collapse') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
    </section>
</div>
