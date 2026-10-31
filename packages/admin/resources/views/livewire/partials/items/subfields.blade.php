{{--
    One list of an item field's fields (EditsItemSettings): sortable rows with Required, a width in
    words and, behind "⋯", per list label and help and Remove; a search to add an existing field,
    and Create a field. $list: "fields" or "types.{index}"; $model: the Livewire path of $rows;
    $flush: rows run edge to edge inside an open block type instead of in a box of their own.
--}}
@php
    $errorPrefix = $list === 'fields' ? 'settings.fields' : "settings.{$list}.fields";
    $listed = array_column($rows, 'name');
    $addable = array_diff_key($subfieldChoices, array_flip($listed));
    $kinds = app(\LynxCms\Admin\Editor\FieldKinds::class);
    $widthWords = [25 => __('lynx-admin::fields.subfields.quarter'), 33 => __('lynx-admin::fields.subfields.third'), 50 => __('lynx-admin::fields.subfields.half'), 66 => __('lynx-admin::fields.subfields.two_thirds'), 75 => __('lynx-admin::fields.subfields.three_quarters'), 100 => __('lynx-admin::fields.subfields.full')];
@endphp
<div class="grid min-w-0 gap-3">
    @if ($rows === [])
        <p class="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-sm">{{ __('lynx-admin::fields.subfields.empty') }}</p>
    @else
        <ol @class(['divide-y', '-mx-4 border-y' => $flush, 'rounded-lg border' => ! $flush]) @if ($canEdit) wire:sort="sortSubfield" @endif>
            @foreach ($rows as $position => $row)
                @php
                    $info = $subfieldChoices[$row['name']] ?? ['label' => $row['name'], 'type' => '', 'translatable' => false];
                    $kind = $kinds->for($info['type']);
                    $shownAs = collect($row['label'])->filter()->first() ?: $info['label'];
                @endphp
                <li wire:key="sub-{{ $list }}-{{ $row['name'] }}" wire:sort:item="{{ $list }}:{{ $row['name'] }}" x-data="{ open: false }" @class(['min-w-0 py-2', 'px-4' => $flush, 'px-3' => ! $flush]) data-subfield="{{ $row['name'] }}">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                        @if ($canEdit) <span wire:sort:handle class="text-muted-foreground cursor-grab select-none active:cursor-grabbing" title="{{ __('lynx-admin::fields.subfields.drag') }}" aria-hidden="true"><x-lucide-grip-vertical class="size-4" /></span> @endif
                        <span class="bg-muted text-muted-foreground hidden size-8 shrink-0 place-items-center rounded-md sm:grid" title="{{ $kind['label'] }}">
                            <x-dynamic-component :component="'lucide-'.$kind['icon']" class="size-4" aria-hidden="true" />
                        </span>
                        <span class="grid min-w-40 flex-1">
                            <span class="truncate font-medium">{{ $shownAs }}@if ($row['required'])<span class="text-destructive" aria-label="{{ __('lynx-admin::fields.subfields.required_mark') }}"> *</span>@endif</span>
                            <span class="text-muted-foreground truncate text-xs"><a href="{{ route('lynx.admin.fields.edit', $row['name']) }}" class="font-mono hover:underline" target="_blank">{{ $row['name'] }}</a> · {{ $kind['label'] }}</span>
                        </span>
                        {{-- The controls take a line of their own when the row is narrow. --}}
                        <span class="ms-auto flex shrink-0 items-center gap-2">
                        <label class="text-muted-foreground flex shrink-0 items-center gap-1.5 text-xs"><input class="blat-checkbox" type="checkbox" wire:model.live="{{ $model }}.{{ $position }}.required"> {{ __('lynx-admin::fields.subfields.required') }}</label>
                        <label class="sr-only" for="width-{{ $list }}-{{ $row['name'] }}">{{ __('lynx-admin::fields.subfields.width_of', ['field' => $shownAs]) }}</label>
                        <select id="width-{{ $list }}-{{ $row['name'] }}" class="blat-select h-8 w-auto shrink-0 text-xs" wire:model.live="{{ $model }}.{{ $position }}.width" title="{{ __('lynx-admin::fields.subfields.width') }}">
                            @foreach (collect(\LynxCms\Core\Templates\EditorSettings::WIDTHS)->push((int) $row['width'])->unique()->sort() as $width)
                                <option value="{{ $width }}">{{ $widthWords[$width] ?? $width.'%' }}</option>
                            @endforeach
                        </select>
                        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="{{ __('lynx-admin::fields.subfields.more_for', ['field' => $shownAs]) }}" title="{{ __('lynx-admin::fields.subfields.more') }}"><x-lucide-ellipsis aria-hidden="true" /></x-lynx-admin::ui.button>
                        </span>
                    </div>
                    <div x-show="open" x-cloak class="@container mt-3 grid gap-3 ps-11">
                        <p class="text-muted-foreground text-xs">{{ __('lynx-admin::fields.subfields.here_only') }}</p>
                        <div class="grid gap-3 @lg:grid-cols-3">
                            @foreach (['label' => $info['label'], 'description' => __('lynx-admin::fields.subfields.own_above'), 'notes' => __('lynx-admin::fields.subfields.own_below')] as $key => $placeholder)
                                <div class="grid content-start gap-1 text-sm">
                                    <label for="sub-{{ $list }}-{{ $row['name'] }}-{{ $key }}" class="font-medium">{{ ['label' => __('lynx-admin::fields.subfields.label'), 'description' => __('lynx-admin::fields.subfields.help_above'), 'notes' => __('lynx-admin::fields.subfields.help_below')][$key] }}</label>
                                    <x-lynx-admin::localized-input id="sub-{{ $list }}-{{ $row['name'] }}-{{ $key }}" :model="$model.'.'.$position.'.'.$key" :placeholder="$placeholder" />
                                </div>
                            @endforeach
                        </div>
                        @if ($canEdit)
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" class="text-destructive hover:text-destructive justify-self-start" wire:click="removeSubfield({{ Js::from($list) }}, {{ Js::from($row['name']) }})">{{ __('lynx-admin::fields.subfields.remove', ['field' => $shownAs]) }}</x-lynx-admin::ui.button>
                        @endif
                    </div>
                    @error("{$errorPrefix}.{$position}.name") <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                </li>
            @endforeach
        </ol>
    @endif
    @error($errorPrefix) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror

    @if ($canEdit)
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-xs" x-data="{ query: '', open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <x-lucide-search class="text-muted-foreground pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2" aria-hidden="true" />
                <input type="search" class="blat-input ps-8" placeholder="{{ __('lynx-admin::fields.subfields.add_existing') }}" x-model="query" x-on:focus="open = true" x-on:input="open = true"
                       aria-label="{{ __('lynx-admin::fields.subfields.add_existing') }}" autocomplete="off">
                <ul x-show="open" x-cloak class="bg-popover text-popover-foreground absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border p-1 shadow-md">
                    @forelse ($addable as $choice => $choiceInfo)
                        <li x-show="@js(Str::lower($choice.' '.$choiceInfo['label'].' '.$choiceInfo['type'])).includes(query.toLowerCase())">
                            <button type="button" class="hover:bg-accent focus:bg-accent flex w-full items-center gap-2 rounded px-2 py-1.5 text-start text-sm"
                                    wire:click="addSubfield(@js($list), @js($choice))" x-on:click="open = false; query = ''">
                                <x-dynamic-component :component="'lucide-'.$kinds->for($choiceInfo['type'])['icon']" class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
                                <span class="truncate">{{ $choiceInfo['label'] }}</span>
                                <span class="text-muted-foreground ms-auto font-mono text-xs">{{ $choice }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="text-muted-foreground px-2 py-1.5 text-sm">{{ __('lynx-admin::fields.subfields.all_listed') }}</li>
                    @endforelse
                </ul>
            </div>
            <span class="text-muted-foreground text-sm">{{ __('lynx-admin::fields.subfields.or') }}</span>
            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="startSubfield({{ Js::from($list) }})"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::fields.subfields.create') }}</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
