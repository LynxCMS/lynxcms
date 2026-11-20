{{-- Repeater and matrix input (ADR-0044): sortable items, switch on/off, indent, clone, remove; one input per subfield and language. --}}
@php
    $definition = lynx()->fields->get($field->name);
    $itemType = $definition->fieldType();
    $isMatrix = $itemType instanceof \LynxCms\Core\Fields\Types\MatrixType;
    $config = $definition->config();
    $items = array_values((array) ($value ?? []));
    $headers = app(\LynxCms\Admin\Editor\ItemHeaders::class)->for($definition, $items, (string) lynx()->languages->default()->code);
    $maxDepth = (int) ($config['max_depth'] ?? 0);
    $max = (int) ($config['max'] ?? 0);
    $collapse = (string) ($config['collapse'] ?? 'new_open');
    $allowed = (array) ($field->settings['allowed_types'] ?? []);
    $types = $isMatrix ? array_values(array_filter($itemType->slugs($config), fn ($slug) => $allowed === [] || in_array($slug, $allowed, true))) : [];
    $addLabel = \LynxCms\Core\Support\LocalizedText::from($config['add_label'] ?? null)->in();
@endphp
<div class="grid gap-2" aria-labelledby="field-{{ $field->name }}">
    @if ($items !== [])
        <ol class="grid gap-2" @unless ($disabled) wire:sort="sortItem" @endunless>
            @foreach ($items as $index => $item)
                @php
                    $key = (string) ($item['key'] ?? $index);
                    $open = $collapse === 'open' || ($collapse === 'new_open' && ($item['new'] ?? false));
                    $subfields = $itemType->subfields($config, $item['type'] ?? null);
                @endphp
                <li wire:key="item-{{ $field->name }}-{{ $key }}" wire:sort:item="{{ $field->name }}:{{ $key }}"
                    x-data="{ open: @js($open) }"
                    @class(['bg-card min-w-0 rounded-md border', 'opacity-60' => ! ($item['enabled'] ?? true)])
                    style="margin-inline-start: {{ ((int) ($item['depth'] ?? 0)) * 1.5 }}rem">
                    <div class="flex flex-wrap items-center gap-2 px-3 py-2 text-sm">
                        @unless ($disabled)
                            <span wire:sort:handle class="cursor-grab select-none text-muted-foreground" title="{{ __('lynx-admin::inputs.items.drag') }}" aria-hidden="true">⠿</span>
                        @endunless
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-start font-medium" @click="open = ! open" :aria-expanded="open">
                            <x-lucide-chevron-right class="size-4 shrink-0 transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
                            <span class="truncate">{{ $headers[$index] ?? '#'.($index + 1) }}</span>
                        </button>
                        @if ($isMatrix)
                            <x-lynx-admin::ui.badge variant="outline">{{ $itemType->typeLabel($config, (string) ($item['type'] ?? '')) }}</x-lynx-admin::ui.badge>
                        @endif
                        @unless ($item['enabled'] ?? true) <x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-admin::inputs.items.off') }}</x-lynx-admin::ui.badge> @endunless
                        @unless ($disabled)
                            <span class="ms-auto flex flex-wrap items-center gap-3 text-xs">
                                <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="toggleItem(@js($field->name), {{ $index }})">{{ __(($item['enabled'] ?? true) ? 'lynx-admin::inputs.items.switch_off' : 'lynx-admin::inputs.items.switch_on') }}</button>
                                @if ($maxDepth > 0)
                                    <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="indentItem(@js($field->name), {{ $index }}, -1)" aria-label="{{ __('lynx-admin::inputs.items.outdent') }}">←</button>
                                    <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="indentItem(@js($field->name), {{ $index }}, 1)" aria-label="{{ __('lynx-admin::inputs.items.indent') }}">→</button>
                                @endif
                                @if ($max === 0 || count($items) < $max)
                                    <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="cloneItem(@js($field->name), {{ $index }})">{{ __('lynx-admin::inputs.items.clone') }}</button>
                                @endif
                                <button type="button" class="text-destructive underline-offset-4 hover:underline" wire:click="removeItem(@js($field->name), {{ $index }})">{{ __('lynx-admin::inputs.items.remove') }}</button>
                            </span>
                        @endunless
                    </div>
                    @error('values.'.$field->name.'.'.$index.'.type') <p data-error-for="{{ 'values.'.$field->name.'.'.$index.'.type' }}" role="alert" class="text-destructive px-3 pb-2 text-sm">{{ $message }}</p> @enderror
                    <div x-show="open" @unless ($open) x-cloak @endunless class="grid grid-cols-12 items-start gap-4 border-t p-3">
                        @foreach (\LynxCms\Core\Fields\Items\SubfieldGroups::of($subfields) as $group)
                            @if ($group->hasHeading())
                                @php($groupId = 'group-'.$field->name.'-'.$index.'-'.$loop->index)
                                <div role="group" aria-labelledby="{{ $groupId }}" class="col-span-12 grid grid-cols-12 items-start gap-4 rounded-md border p-3" data-item-group>
                                    <div class="col-span-12 grid gap-1">
                                        <p id="{{ $groupId }}" class="text-sm font-semibold">{{ $group->label }}</p>
                                        @if ($group->description !== '')
                                            <p class="lynx-field-help">{{ $group->description }}</p>
                                        @endif
                                    </div>
                                    @include('lynx-admin::inputs.partials.item-subfields', ['subfields' => $group->subfields])
                                    @if ($group->notes !== '' || $group->hasLink())
                                        <div class="lynx-field-help col-span-12 grid gap-1">
                                            @if ($group->notes !== '')
                                                <p class="whitespace-pre-line">{{ $group->notes }}</p>
                                            @endif
                                            @if ($group->hasLink())
                                                <p><a href="{{ $group->linkUrl }}" target="_blank" rel="noopener" class="text-primary underline-offset-4 hover:underline">{{ $group->linkLabel }}</a></p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @else
                                @include('lynx-admin::inputs.partials.item-subfields', ['subfields' => $group->subfields])
                            @endif
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::inputs.items.empty') }}</p>
    @endif

    @unless ($disabled || ($max > 0 && count($items) >= $max))
        <div class="flex flex-wrap gap-2">
            @if ($isMatrix)
                @foreach ($types as $slug)
                    <x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="addItem({{ Js::from($field->name) }}, {{ Js::from($slug) }})"><x-lucide-plus aria-hidden="true" /> {{ $itemType->typeLabel($config, $slug) }}</x-lynx-admin::ui.button>
                @endforeach
            @else
                <x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="addItem({{ Js::from($field->name) }})"><x-lucide-plus aria-hidden="true" /> {{ $addLabel !== '' ? $addLabel : __('lynx-admin::inputs.items.add') }}</x-lynx-admin::ui.button>
            @endif
        </div>
    @endunless
</div>
