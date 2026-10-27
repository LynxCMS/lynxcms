{{-- A matrix's block types as cards: sortable, each opening to its label, slug, item header and fields. --}}
@if ($itemTypes === [])
    <x-lynx-admin::ui.empty class="border">
        <x-lynx-admin::ui.empty-header>
            <x-lynx-admin::ui.empty-media variant="icon"><x-lucide-blocks aria-hidden="true" /></x-lynx-admin::ui.empty-media>
            <x-lynx-admin::ui.empty-title>No block types yet</x-lynx-admin::ui.empty-title>
            <x-lynx-admin::ui.empty-description>Add a type for each kind of block editors may place, then choose the fields it shows.</x-lynx-admin::ui.empty-description>
        </x-lynx-admin::ui.empty-header>
        @if ($canEdit)
            <x-lynx-admin::ui.button type="button" wire:click="addType"><x-lucide-plus aria-hidden="true" /> Add block type</x-lynx-admin::ui.button>
        @endif
    </x-lynx-admin::ui.empty>
@else
    <ol class="grid gap-3" @if ($canEdit) wire:sort="sortType" @endif>
        @foreach ($itemTypes as $index => $blockType)
            @php
                $saved = $savedSlugs[$blockType['key']] ?? null;
                $title = collect($blockType['label'])->filter()->first() ?: ($blockType['slug'] !== '' ? $blockType['slug'] : 'New block type');
                $used = $saved === null ? 0 : ($typeUsage[$saved] ?? 0);
                $hasErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, "settings.types.{$index}."));
            @endphp
            <li wire:key="type-{{ $blockType['key'] }}" wire:sort:item="{{ $blockType['key'] }}" x-data="{ open: @js($saved === null || $hasErrors) }"
                @class(['bg-card min-w-0 rounded-lg border shadow-xs', 'border-destructive' => $hasErrors])>
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    @if ($canEdit) <span wire:sort:handle class="cursor-grab select-none text-muted-foreground active:cursor-grabbing" title="Drag to reorder" aria-hidden="true">⠿</span> @endif
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-start" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
                        <span class="bg-primary/10 text-primary grid size-7 shrink-0 place-items-center rounded-md text-xs font-semibold">{{ $index + 1 }}</span>
                        <span class="truncate font-medium">{{ $title }}</span>
                        @if ($blockType['slug'] !== '' || $saved !== null)
                            <span class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-mono text-xs">
                                @if ($saved !== null) <x-lucide-lock class="size-3" aria-label="Fixed" /> @endif{{ $saved ?? $blockType['slug'] }}
                            </span>
                        @endif
                    </button>
                    <span class="text-muted-foreground text-sm">{{ count($blockType['fields']) }} {{ Str::plural('field', count($blockType['fields'])) }}</span>
                    @if ($used > 0) <x-lynx-admin::ui.badge variant="secondary">On {{ $used }} {{ Str::plural('page', $used) }}</x-lynx-admin::ui.badge> @endif
                    @if ($canEdit)
                        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" class="hover:text-destructive" wire:click="removeType({{ Js::from($blockType['key']) }})"
                            :wire:confirm="$used > 0 ? $used.' '.Str::plural('page', $used).' have blocks of this type, and cannot be saved until those blocks are removed or the type is added back. Remove the type?' : null"
                            aria-label="Remove block type {{ $title }}" title="Remove"><x-lucide-trash-2 aria-hidden="true" /></x-lynx-admin::ui.button>
                    @endif
                </div>
                <div x-show="open" @if ($saved !== null && ! $hasErrors) x-cloak @endif class="grid gap-5 border-t p-4 sm:p-6">
                    <x-lynx-admin::builder-panel title="Name" icon="tag" description="What editors read on the add button and on each block.">
                        <div class="grid max-w-md gap-1 text-sm">
                            <label for="type-{{ $blockType['key'] }}-label" class="font-medium">Label</label>
                            <x-lynx-admin::localized-input id="type-{{ $blockType['key'] }}-label" model="itemTypes.{{ $index }}.label" placeholder="e.g. Text, Image or Quote" />
                            @error("settings.types.{$index}.label") <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid max-w-md gap-1 text-sm">
                            <label for="type-{{ $blockType['key'] }}-slug" class="font-medium">Slug</label>
                            @if ($saved !== null)
                                <p id="type-{{ $blockType['key'] }}-slug" class="flex items-center gap-1.5 font-mono"><x-lucide-lock class="text-muted-foreground size-3.5" aria-hidden="true" /> {{ $saved }}</p>
                                <p class="text-muted-foreground">Fixed, so blocks keep their type. Its blocks render with:</p>
                                <code class="bg-background block w-fit max-w-full rounded border px-2 py-1 text-xs break-words">{{ str_replace('.', '/', (string) config('lynx.views_path')) }}/fields/{{ $field ?? $name }}/{{ $saved }}.blade.php</code>
                            @else
                                <input id="type-{{ $blockType['key'] }}-slug" type="text" class="blat-input font-mono" wire:model="itemTypes.{{ $index }}.slug" placeholder="From the label" autocomplete="off"
                                       @error("settings.types.{$index}.slug") aria-invalid="true" @enderror>
                                <p class="text-muted-foreground">Names the type in code. Lowercase letters, digits, dashes and underscores; it cannot change once saved.</p>
                            @endif
                            @error("settings.types.{$index}.slug") <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        </div>
                    </x-lynx-admin::builder-panel>

                    <x-lynx-admin::builder-panel title="Fields" icon="list" description="Each block of this type shows these fields, in this order.">
                        @include('lynx-admin::livewire.partials.items.subfields', ['list' => "types.{$index}", 'model' => "itemTypes.{$index}.fields", 'rows' => $blockType['fields']])
                    </x-lynx-admin::builder-panel>

                    <x-lynx-admin::builder-panel title="In the page editor" icon="eye" description="How a block of this type looks to editors.">
                        <div class="grid items-start gap-5 lg:grid-cols-2">
                            <div class="grid gap-1 text-sm">
                                <label for="type-{{ $blockType['key'] }}-item-label" class="font-medium">Block header</label>
                                <input id="type-{{ $blockType['key'] }}-item-label" type="text" class="blat-input font-mono" wire:model="itemTypes.{{ $index }}.item_label"
                                       placeholder="{{ isset($blockType['fields'][0]) ? '{'.$blockType['fields'][0]['name'].'}' : '{field}' }}" autocomplete="off">
                                <p class="text-muted-foreground">What a closed block shows. Field names in braces show their values.</p>
                            </div>
                            @include('lynx-admin::livewire.partials.items.block-mock', ['title' => $title, 'rows' => $blockType['fields']])
                        </div>
                    </x-lynx-admin::builder-panel>
                </div>
            </li>
        @endforeach
    </ol>
    @if ($canEdit)
        <button type="button" wire:click="addType" class="border-border text-muted-foreground hover:border-primary hover:text-primary flex items-center justify-center gap-2 rounded-lg border border-dashed p-3 text-sm font-medium">
            <x-lucide-plus class="size-4" aria-hidden="true" /> Add block type
        </button>
    @endif
@endif
