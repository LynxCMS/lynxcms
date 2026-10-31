{{-- A matrix's block types as an accordion: sortable rows, each opening to its name, fields and closed header. --}}
@if ($itemTypes === [])
    <x-lynx-admin::ui.empty class="border">
        <x-lynx-admin::ui.empty-header>
            <x-lynx-admin::ui.empty-media variant="icon"><x-lucide-blocks aria-hidden="true" /></x-lynx-admin::ui.empty-media>
            <x-lynx-admin::ui.empty-title>{{ __('lynx-admin::fields.types.empty') }}</x-lynx-admin::ui.empty-title>
            <x-lynx-admin::ui.empty-description>{{ __('lynx-admin::fields.types.empty_help') }}</x-lynx-admin::ui.empty-description>
        </x-lynx-admin::ui.empty-header>
        @if ($canEdit)
            <x-lynx-admin::ui.button type="button" wire:click="addType"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::fields.types.add') }}</x-lynx-admin::ui.button>
        @endif
    </x-lynx-admin::ui.empty>
@else
    <ol class="grid gap-2" @if ($canEdit) wire:sort="sortType" @endif>
        @foreach ($itemTypes as $index => $blockType)
            @php
                $saved = $savedSlugs[$blockType['key']] ?? null;
                $title = collect($blockType['label'])->filter()->first() ?: ($blockType['slug'] !== '' ? $blockType['slug'] : __('lynx-admin::fields.types.new'));
                $used = $saved === null ? 0 : ($typeUsage[$saved] ?? 0);
                $hasErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, "settings.types.{$index}."));
            @endphp
            <li wire:key="type-{{ $blockType['key'] }}" wire:sort:item="{{ $blockType['key'] }}" x-data="{ open: @js($saved === null || $hasErrors || $loop->first) }" data-block-type="{{ $saved ?? $blockType['slug'] }}"
                @class(['bg-card min-w-0 rounded-lg border', 'border-destructive' => $hasErrors]) x-bind:class="open && ! @js($hasErrors) && 'border-foreground/20'">
                <div class="flex items-center gap-3 px-3 py-2.5">
                    @if ($canEdit) <span wire:sort:handle class="text-muted-foreground cursor-grab select-none active:cursor-grabbing" title="{{ __('lynx-admin::fields.subfields.drag') }}" aria-hidden="true"><x-lucide-grip-vertical class="size-4" /></span> @endif
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-start" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span class="bg-muted text-muted-foreground grid size-8 shrink-0 place-items-center rounded-md" x-bind:class="open && 'bg-primary! text-primary-foreground!'"><x-lucide-blocks class="size-4" aria-hidden="true" /></span>
                        <span class="grid min-w-0 gap-0.5">
                            <span class="truncate font-medium">{{ $title }}</span>
                            <span class="text-muted-foreground text-xs">{{ trans_choice('lynx-admin::fields.types.fields', count($blockType['fields'])) }}@if ($used > 0) · {{ trans_choice('lynx-admin::fields.types.on_pages', $used) }}@endif</span>
                        </span>
                        <x-lucide-chevron-right class="text-muted-foreground ms-auto size-4 shrink-0 transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
                    </button>
                    @if ($canEdit)
                        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" class="hover:text-destructive" wire:click="removeType({{ Js::from($blockType['key']) }})"
                            :wire:confirm="$used > 0 ? trans_choice('lynx-admin::fields.types.confirm_remove', $used) : null"
                            aria-label="{{ __('lynx-admin::fields.types.remove_named', ['name' => $title]) }}" title="{{ __('lynx-admin::fields.types.remove') }}"><x-lucide-trash-2 aria-hidden="true" /></x-lynx-admin::ui.button>
                    @endif
                </div>
                <div x-show="open" @if (! ($saved === null || $hasErrors || $loop->first)) x-cloak @endif class="grid gap-5 border-t px-4 pt-4 pb-5">
                    <div class="grid gap-1.5">
                        <label for="type-{{ $blockType['key'] }}-label" class="text-sm font-medium">{{ __('lynx-admin::fields.types.editors_see') }}</label>
                        <x-lynx-admin::localized-input id="type-{{ $blockType['key'] }}-label" model="itemTypes.{{ $index }}.label" :placeholder="__('lynx-admin::fields.types.editors_see_placeholder')" />
                        @error("settings.types.{$index}.label") <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        @if ($saved !== null)
                            <p class="text-muted-foreground flex flex-wrap items-center gap-1.5 text-sm"><x-lucide-lock class="size-3.5" aria-hidden="true" /><span class="font-mono">{{ $saved }}</span> {{ __('lynx-admin::fields.types.renders_with') }}
                                <code class="text-xs break-all">{{ str_replace('.', '/', (string) config('lynx.views_path')) }}/fields/{{ $field ?? $name }}/{{ $saved }}.blade.php</code></p>
                        @else
                            <label for="type-{{ $blockType['key'] }}-slug" class="mt-2 text-sm font-medium">{{ __('lynx-admin::fields.types.in_code') }}</label>
                            <input id="type-{{ $blockType['key'] }}-slug" type="text" class="blat-input max-w-md font-mono" wire:model="itemTypes.{{ $index }}.slug" placeholder="{{ __('lynx-admin::fields.types.in_code_placeholder') }}" autocomplete="off"
                                   @error("settings.types.{$index}.slug") aria-invalid="true" @enderror>
                            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::fields.types.in_code_help') }}</p>
                            @error("settings.types.{$index}.slug") <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        @endif
                    </div>
                    @include('lynx-admin::livewire.partials.items.subfields', ['list' => "types.{$index}", 'model' => "itemTypes.{$index}.fields", 'rows' => $blockType['fields'], 'flush' => true])
                    <div class="grid gap-1.5">
                        <label for="type-{{ $blockType['key'] }}-item-label" class="text-sm font-medium">{{ __('lynx-admin::fields.types.closed') }}</label>
                        <input id="type-{{ $blockType['key'] }}-item-label" type="text" class="blat-input max-w-md font-mono" wire:model="itemTypes.{{ $index }}.item_label"
                               placeholder="{{ isset($blockType['fields'][0]) ? '{'.$blockType['fields'][0]['name'].'}' : '{field}' }}" autocomplete="off">
                        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::fields.types.closed_help') }}</p>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
    @if ($canEdit)
        <button type="button" wire:click="addType" class="text-muted-foreground hover:border-primary hover:text-foreground flex items-center justify-center gap-2 rounded-lg border border-dashed p-2.5 text-sm font-medium">
            <x-lucide-plus class="size-4" aria-hidden="true" /> {{ __('lynx-admin::fields.types.add') }}
        </button>
    @endif
@endif
