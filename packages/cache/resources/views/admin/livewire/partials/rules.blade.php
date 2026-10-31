{{--
    Each template's rule, saved through page_cache.save_rules: the default lifetime as a sentence,
    then one row per template that sums its rule up and opens to edit it. Save is in the heading.
--}}
<form id="cache-rules" wire:submit="save" class="grid gap-8">
    <section class="grid gap-3" aria-labelledby="cache-lifetime">
        <h2 id="cache-lifetime" class="text-base font-semibold">{{ __('lynx-cache::admin.rules.how_long') }}</h2>
        <p class="flex flex-wrap items-center gap-2 text-sm">
            <label for="cache-default-lifetime">{{ __('lynx-cache::admin.rules.default_label') }}</label>
            <input id="cache-default-lifetime" type="number" min="0" class="blat-input w-28" wire:model="lifetime" placeholder="{{ $configLifetime }}" @error('lifetime') aria-invalid="true" @enderror>
            <span>{{ __('lynx-cache::admin.rules.seconds_end') }}</span>
        </p>
        <p class="text-muted-foreground text-sm">{{ __('lynx-cache::admin.rules.default_help', ['seconds' => $configLifetime]) }}</p>
        @error('lifetime') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    </section>

    <section class="grid gap-3 border-t pt-6" aria-labelledby="cache-templates">
        <h2 id="cache-templates" class="text-base font-semibold">{{ __('lynx-cache::admin.rules.templates') }}</h2>
        @error('templates') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        <ol class="grid gap-2">
            @foreach ($templates as $name => $label)
                @php($hasErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, "templates.{$name}.")))
                <li wire:key="rule-{{ $name }}" x-data="{ open: @js($hasErrors) }" @class(['bg-card rounded-lg border', 'border-destructive' => $hasErrors])>
                    <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-start" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span @class(['size-2 shrink-0 rounded-full', 'bg-success' => $rules[$name]['cache'], 'border-muted-foreground border' => ! $rules[$name]['cache']]) data-rule-cached="{{ $name }}" aria-hidden="true"></span>
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="font-medium">{{ $label }} <span class="text-muted-foreground font-mono text-xs font-normal">{{ $name }}</span></span>
                            <span class="text-muted-foreground text-sm" data-rule-summary="{{ $name }}">{{ $summaries[$name] }}</span>
                        </span>
                        <x-lucide-chevron-right class="text-muted-foreground size-4 shrink-0 transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
                    </button>
                    <div x-show="open" x-cloak class="grid gap-6 border-t px-4 pt-5 pb-6 text-sm">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" class="blat-checkbox mt-0.5" wire:model.live="rules.{{ $name }}.cache">
                            <span class="grid gap-0.5"><span class="font-medium">{{ __('lynx-cache::admin.rules.store') }}</span><span class="text-muted-foreground">{{ __('lynx-cache::admin.rules.store_help') }}</span></span>
                        </label>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="grid content-start gap-1.5">
                                <label for="rule-{{ $name }}-lifetime" class="font-medium">{{ __('lynx-cache::admin.rules.keep') }}</label>
                                <div class="flex items-center gap-2"><input id="rule-{{ $name }}-lifetime" type="number" min="0" class="blat-input w-28" wire:model="rules.{{ $name }}.lifetime" placeholder="{{ __('lynx-cache::admin.rules.default') }}"><span class="text-muted-foreground">{{ __('lynx-cache::admin.rules.seconds') }}</span></div>
                                <p class="text-muted-foreground">{{ __('lynx-cache::admin.rules.empty_default') }}</p>
                                @error('templates.'.$name.'.lifetime') <p role="alert" class="text-destructive">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid content-start gap-1.5">
                                <label for="rule-{{ $name }}-parameters" class="font-medium">{{ __('lynx-cache::admin.rules.parameters') }}</label>
                                <input id="rule-{{ $name }}-parameters" type="text" class="blat-input" wire:model="rules.{{ $name }}.parameters" placeholder="page, sort">
                                <p class="text-muted-foreground">{{ __('lynx-cache::admin.rules.parameters_help') }}</p>
                                @error('templates.'.$name.'.parameters.*') <p role="alert" class="text-destructive">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <fieldset class="grid gap-2">
                            <legend class="mb-1.5 font-medium">{{ __('lynx-cache::admin.rules.clear_legend') }}</legend>
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                @foreach ($scopes as $scope)
                                    <label class="flex items-center gap-2"><input type="checkbox" class="blat-checkbox" value="{{ $scope }}" wire:model.live="rules.{{ $name }}.clear"> {{ \LynxCms\Cache\Admin\Livewire\PageCacheScreen::scopeWords($scope) }}</label>
                                @endforeach
                            </div>
                            <p class="text-muted-foreground">{{ __('lynx-cache::admin.rules.none_ticked') }}</p>
                        </fieldset>
                        <details class="grid gap-3 border-t pt-4" wire:ignore.self>
                            <summary class="w-fit cursor-pointer font-medium">{{ __('lynx-cache::admin.rules.advanced') }}</summary>
                            <label class="mt-3 flex cursor-pointer items-start gap-3">
                                <input type="checkbox" class="blat-checkbox mt-0.5" wire:model="rules.{{ $name }}.csrf">
                                <span class="grid gap-0.5"><span class="font-medium">{{ __('lynx-cache::admin.rules.forms') }}</span><span class="text-muted-foreground">{{ __('lynx-cache::admin.rules.forms_help') }}</span></span>
                            </label>
                        </details>
                    </div>
                </li>
            @endforeach
        </ol>
        @if ($statusFor === 'rules') <p role="status" class="text-muted-foreground text-sm">{{ $status }}</p> @endif
    </section>
</form>
