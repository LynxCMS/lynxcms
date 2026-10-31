{{-- A repeater's or matrix's Limits and display tab (EditsItemDisplay): how many, how deep, the add button and how items start. --}}
@php($noun = $type === 'matrix' ? 'block' : 'item')
<div class="grid max-w-3xl gap-8" data-item-limits>
    <section class="grid gap-4" aria-labelledby="limits-count">
        <h2 id="limits-count" class="text-base font-semibold">{{ __('lynx-admin::fields.limits.how_many') }}</h2>
        <p class="flex flex-wrap items-center gap-2 text-sm">
            <label for="setting-limit-min">{{ __('lynx-admin::fields.limits.at_least') }}</label>
            <input id="setting-limit-min" type="number" min="0" class="blat-input w-20" wire:model="settings.min" placeholder="0" @error('settings.min') aria-invalid="true" @enderror>
            <label for="setting-limit-max">{{ __('lynx-admin::fields.limits.at_most') }}</label>
            <input id="setting-limit-max" type="number" min="0" class="blat-input w-20" wire:model="settings.max" placeholder="{{ __('lynx-admin::fields.limits.any') }}" @error('settings.max') aria-invalid="true" @enderror>
            <span>{{ __('lynx-admin::fields.limits.'.Str::plural($noun)) }}</span>
        </p>
        @foreach (['settings.min', 'settings.max'] as $key) @error($key) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror @endforeach
        <p class="flex flex-wrap items-center gap-2 text-sm">
            <label for="setting-limit-depth">{{ __('lynx-admin::fields.limits.indent_'.$noun) }}</label>
            <select id="setting-limit-depth" class="blat-select w-auto" wire:model="settings.max_depth">
                <option value="">{{ __('lynx-admin::fields.limits.not_at_all') }}</option>
                @foreach (range(1, 5) as $depth)
                    <option value="{{ $depth }}">{{ trans_choice('lynx-admin::fields.limits.levels', $depth) }}</option>
                @endforeach
            </select>
            <span>{{ __('lynx-admin::fields.limits.under') }}</span>
        </p>
        @error('settings.max_depth') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    </section>

    <section class="grid gap-5 border-t pt-6" aria-labelledby="limits-display">
        <h2 id="limits-display" class="text-base font-semibold">{{ __('lynx-admin::fields.limits.in_editor') }}</h2>
        <div class="grid gap-1.5">
            <label for="setting-limit-add" class="text-sm font-medium">{{ __('lynx-admin::fields.limits.add_text') }}</label>
            <x-lynx-admin::localized-input id="setting-limit-add" model="addLabel" :placeholder="__('lynx-admin::fields.limits.add')" />
            @error('settings.add_label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        @if ($type === 'matrix')
            <div class="grid gap-1.5">
                <label for="setting-limit-header" class="text-sm font-medium">{{ __('lynx-admin::fields.limits.closed_block_default') }}</label>
                <input id="setting-limit-header" type="text" class="blat-input max-w-md font-mono" wire:model="settings.item_label" autocomplete="off">
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::fields.limits.braces') }}</p>
            </div>
        @endif
        <div class="grid gap-1.5" role="radiogroup" aria-labelledby="limits-start">
            <span id="limits-start" class="text-sm font-medium">{{ __('lynx-admin::fields.limits.'.Str::plural($noun).'_start') }}</span>
            @foreach (['' => [__('lynx-admin::fields.limits.closed_new'), __('lynx-admin::fields.limits.closed_new_hint')], 'open' => [__('lynx-admin::fields.limits.open'), __('lynx-admin::fields.limits.open_'.Str::plural($noun))], 'collapsed' => [__('lynx-admin::fields.limits.closed'), __('lynx-admin::fields.limits.closed_hint')]] as $value => [$title, $hint])
                <label class="has-checked:border-primary has-checked:bg-primary/5 flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                    <input type="radio" class="mt-0.5 accent-primary" name="setting-collapse" value="{{ $value }}" wire:model="settings.collapse">
                    <span class="grid gap-0.5"><span class="font-medium">{{ $title }}</span><span class="text-muted-foreground">{{ $hint }}</span></span>
                </label>
            @endforeach
            @error('settings.collapse') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
    </section>
</div>
