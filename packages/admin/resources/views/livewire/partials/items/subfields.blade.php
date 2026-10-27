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
    $widthWords = [25 => 'Quarter', 33 => 'Third', 50 => 'Half', 66 => 'Two thirds', 75 => 'Three quarters', 100 => 'Full'];
@endphp
<div class="grid min-w-0 gap-3">
    @if ($rows === [])
        <p class="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-sm">No fields yet. Add an existing field below, or create one.</p>
    @else
        <ol @class(['divide-y', '-mx-4 border-y' => $flush, 'rounded-lg border' => ! $flush]) @if ($canEdit) wire:sort="sortSubfield" @endif>
            @foreach ($rows as $position => $row)
                @php
                    $info = $subfieldChoices[$row['name']] ?? ['label' => $row['name'], 'type' => '', 'translatable' => false];
                    $kind = $kinds->for($info['type']);
                    $shownAs = collect($row['label'])->filter()->first() ?: $info['label'];
                @endphp
                <li wire:key="sub-{{ $list }}-{{ $row['name'] }}" wire:sort:item="{{ $list }}:{{ $row['name'] }}" x-data="{ open: false }" @class(['min-w-0 py-2', 'px-4' => $flush, 'px-3' => ! $flush]) data-subfield="{{ $row['name'] }}">
                    <div class="flex items-center gap-3 text-sm">
                        @if ($canEdit) <span wire:sort:handle class="text-muted-foreground cursor-grab select-none active:cursor-grabbing" title="Drag to reorder" aria-hidden="true"><x-lucide-grip-vertical class="size-4" /></span> @endif
                        <span class="bg-muted text-muted-foreground grid size-8 shrink-0 place-items-center rounded-md" title="{{ $kind['label'] }}">
                            <x-dynamic-component :component="'lucide-'.$kind['icon']" class="size-4" aria-hidden="true" />
                        </span>
                        <span class="grid min-w-0 flex-1">
                            <span class="truncate font-medium">{{ $shownAs }}@if ($row['required'])<span class="text-destructive" aria-label="required"> *</span>@endif</span>
                            <span class="text-muted-foreground truncate text-xs"><a href="{{ route('lynx.admin.fields.edit', $row['name']) }}" class="font-mono hover:underline" target="_blank">{{ $row['name'] }}</a> · {{ $kind['label'] }}</span>
                        </span>
                        <label class="text-muted-foreground flex shrink-0 items-center gap-1.5 text-xs"><input class="blat-checkbox" type="checkbox" wire:model.live="{{ $model }}.{{ $position }}.required"> Required</label>
                        <label class="sr-only" for="width-{{ $list }}-{{ $row['name'] }}">Width of {{ $shownAs }} in the page editor</label>
                        <select id="width-{{ $list }}-{{ $row['name'] }}" class="blat-select h-8 w-auto shrink-0 text-xs" wire:model.live="{{ $model }}.{{ $position }}.width" title="Width in the page editor">
                            @foreach (collect(\LynxCms\Core\Templates\EditorSettings::WIDTHS)->push((int) $row['width'])->unique()->sort() as $width)
                                <option value="{{ $width }}">{{ $widthWords[$width] ?? $width.'%' }}</option>
                            @endforeach
                        </select>
                        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="Label, help and remove for {{ $shownAs }}" title="Label, help and remove"><x-lucide-ellipsis aria-hidden="true" /></x-lynx-admin::ui.button>
                    </div>
                    <div x-show="open" x-cloak class="@container mt-3 grid gap-3 ps-11">
                        <p class="text-muted-foreground text-xs">Here only: leave empty to use the field's own.</p>
                        <div class="grid gap-3 @lg:grid-cols-3">
                            @foreach (['label' => $info['label'], 'description' => "The field's own help above", 'notes' => "The field's own help below"] as $key => $placeholder)
                                <div class="grid content-start gap-1 text-sm">
                                    <label for="sub-{{ $list }}-{{ $row['name'] }}-{{ $key }}" class="font-medium">{{ ['label' => 'Label', 'description' => 'Help above', 'notes' => 'Help below'][$key] }}</label>
                                    <x-lynx-admin::localized-input id="sub-{{ $list }}-{{ $row['name'] }}-{{ $key }}" :model="$model.'.'.$position.'.'.$key" :placeholder="$placeholder" />
                                </div>
                            @endforeach
                        </div>
                        @if ($canEdit)
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" class="text-destructive hover:text-destructive justify-self-start" wire:click="removeSubfield({{ Js::from($list) }}, {{ Js::from($row['name']) }})">Remove {{ $shownAs }} from this list</x-lynx-admin::ui.button>
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
                <input type="search" class="blat-input ps-8" placeholder="Add an existing field" x-model="query" x-on:focus="open = true" x-on:input="open = true"
                       aria-label="Add an existing field" autocomplete="off">
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
                        <li class="text-muted-foreground px-2 py-1.5 text-sm">Every field is listed already.</li>
                    @endforelse
                </ul>
            </div>
            <span class="text-muted-foreground text-sm">or</span>
            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="startSubfield({{ Js::from($list) }})"><x-lucide-plus aria-hidden="true" /> Create a field</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
