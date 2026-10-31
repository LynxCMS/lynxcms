{{-- One field of the layout builder, with its overrides for this template and the Place select. --}}
<li wire:key="row-{{ $field }}" wire:sort:item="field:{{ $field }}" x-data="{ open: false }" class="@container bg-card min-w-0 px-4 py-3">
    <div class="flex flex-wrap items-center gap-3">
        @if ($canEdit) <span wire:sort:handle class="cursor-grab select-none text-muted-foreground active:cursor-grabbing" title="{{ __('lynx-admin::templates.row.drag') }}" aria-hidden="true">⠿</span> @endif
        <a href="{{ route('lynx.admin.fields.edit', $field) }}" class="font-medium">{{ $field }}</a>
        <span class="text-sm text-muted-foreground">{{ $fieldInfo[$field]['type'] ?? '' }}@php($shownAs = collect($rows[$field]['label'] ?? [])->filter()->first()){{ $shownAs ? __('lynx-admin::templates.row.shown_as', ['label' => $shownAs]) : '' }}</span>
        @if ($rows[$field]['required'] ?? false) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::templates.row.required') }}</x-lynx-admin::ui.badge> @endif
        @if ($rows[$field]['searchable'] ?? false) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::templates.row.searchable') }}</x-lynx-admin::ui.badge> @endif
        @if (($rows[$field]['show_if'] ?? '') !== '' || ($rows[$field]['required_if'] ?? '') !== '') <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::templates.row.conditional') }}</x-lynx-admin::ui.badge> @endif
        @if (! in_array($rows[$field]['editor'] ?? 'open', ['', 'open'], true)) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::templates.row.'.$rows[$field]['editor']) }}</x-lynx-admin::ui.badge> @endif
        @if (($rows[$field]['width'] ?? '100') !== '100') <x-lynx-admin::ui.badge variant="outline">{{ $rows[$field]['width'] }}%</x-lynx-admin::ui.badge> @endif
        <span class="ms-auto flex items-center gap-1 text-sm">
            @if ($canEdit)
                <label class="sr-only" for="place-field-{{ $field }}">{{ __('lynx-admin::templates.row.move_field', ['field' => $field]) }}</label>
                <select id="place-field-{{ $field }}" class="blat-select h-8 w-32 text-xs" x-on:change="$wire.placeItem(@js('field:'.$field), $event.target.value)">
                    <option value="">{{ __('lynx-admin::templates.row.move_to') }}</option>
                    @foreach ($places + $sectionPlaces as $value => $placeLabel)
                        <option value="{{ $value }}">{{ $placeLabel }}</option>
                    @endforeach
                </select>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="{{ __('lynx-admin::templates.row.overrides_for', ['field' => $field]) }}" title="{{ __('lynx-admin::templates.row.overrides') }}"><x-lucide-sliders-horizontal aria-hidden="true" /></x-lynx-admin::ui.button>
            @if ($canEdit) <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" class="hover:text-destructive" wire:click="removeField({{ Js::from($field) }})" aria-label="{{ __('lynx-admin::templates.row.remove_field', ['field' => $field]) }}" title="{{ __('lynx-admin::templates.row.remove') }}"><x-lucide-x aria-hidden="true" /></x-lynx-admin::ui.button> @endif
        </span>
    </div>
    <div x-show="open" x-cloak class="mt-3 grid gap-3 @lg:grid-cols-2">
        <div class="grid gap-1 text-sm">
            <label for="override-{{ $field }}-label" class="font-medium">{{ __('lynx-admin::templates.row.label') }}</label>
            <x-lynx-admin::localized-input id="override-{{ $field }}-label" model="rows.{{ $field }}.label" :placeholder="$fieldInfo[$field]['label'] ?? ''" />
        </div>
        <div class="grid gap-1 text-sm">
            <label for="override-{{ $field }}-description" class="font-medium">{{ __('lynx-admin::templates.row.description') }}</label>
            <x-lynx-admin::localized-input id="override-{{ $field }}-description" model="rows.{{ $field }}.description" :placeholder="__('lynx-admin::templates.row.own_description')" />
        </div>
        <div class="grid gap-1 text-sm">
            <label for="override-{{ $field }}-notes" class="font-medium">{{ __('lynx-admin::templates.row.notes') }}</label>
            <x-lynx-admin::localized-input id="override-{{ $field }}-notes" model="rows.{{ $field }}.notes" :placeholder="__('lynx-admin::templates.row.own_notes')" />
        </div>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-admin::templates.row.show_if') }}</span>
            <input id="override-{{ $field }}-show-if" type="text" class="blat-input font-mono" wire:model="rows.{{ $field }}.show_if" placeholder="format=onsite|hybrid">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-admin::templates.row.required_if') }}</span>
            <input id="override-{{ $field }}-required-if" type="text" class="blat-input font-mono" wire:model="rows.{{ $field }}.required_if" placeholder="price>0">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-admin::templates.row.in_editor') }}</span>
            <select id="override-{{ $field }}-editor" class="blat-select" wire:model="rows.{{ $field }}.editor">
                <option value="open">{{ __('lynx-admin::templates.row.open') }}</option>
                <option value="collapsed">{{ __('lynx-admin::templates.row.opt_collapsed') }}</option>
                <option value="collapsed_blank">{{ __('lynx-admin::templates.row.opt_collapsed_blank') }}</option>
                <option value="collapsed_populated">{{ __('lynx-admin::templates.row.opt_collapsed_populated') }}</option>
                <option value="locked">{{ __('lynx-admin::templates.row.opt_locked') }}</option>
                <option value="hidden">{{ __('lynx-admin::templates.row.opt_hidden') }}</option>
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-admin::templates.row.width') }}</span>
            <select id="override-{{ $field }}-width" class="blat-select" wire:model="rows.{{ $field }}.width">
                @foreach (\LynxCms\Core\Templates\EditorSettings::WIDTHS as $width)
                    <option value="{{ $width }}">{{ $width }}%</option>
                @endforeach
            </select>
        </label>
        <div class="grid gap-1 text-sm">
            <label for="override-{{ $field }}-placeholder" class="font-medium">{{ __('lynx-admin::templates.row.placeholder') }}</label>
            <x-lynx-admin::localized-input id="override-{{ $field }}-placeholder" model="rows.{{ $field }}.placeholder" :placeholder="__('lynx-admin::templates.row.placeholder_hint')" />
        </div>
        <div class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-admin::templates.row.icon') }}</span>
            <livewire:lynx-admin.icon-picker wire:model="rows.{{ $field }}.icon" :disabled="! $canEdit" wire:key="override-icon-{{ $field }}" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" id="override-{{ $field }}-required" type="checkbox" wire:model="rows.{{ $field }}.required"> {{ __(lynx()->languages->isMultilingual() ? 'lynx-admin::templates.row.required_default' : 'lynx-admin::templates.row.required_check') }}</label>
        <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" id="override-{{ $field }}-searchable" type="checkbox" wire:model="rows.{{ $field }}.searchable"> {{ __('lynx-admin::templates.row.searchable_check') }}</label>
    </div>
</li>
