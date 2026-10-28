{{--
    The kind's own settings, from its settings schema in words (SettingLabels), then its formatters
    as switches. A repeater's or matrix's limits are on their own tab (EditsItemDisplay).
--}}
@php
    $shown = $hasItemBuilder ? array_diff_key($definitions, array_flip(\LynxCms\Admin\Livewire\FieldForm::ITEM_DISPLAY_SETTINGS)) : $definitions;
    $labels = app(\LynxCms\Admin\Editor\SettingLabels::class);
@endphp
@if ($shown !== [] || $availableFormatters !== [])
    <section class="grid gap-5 border-t pt-6" aria-labelledby="field-settings">
        <h2 id="field-settings" class="text-base font-semibold">{{ $kind['label'] }}</h2>
        @foreach ($shown as $setting => $definition)
            @php($words = $labels->for($setting, $definition))
            <div class="grid gap-1.5" wire:key="setting-{{ $type }}-{{ $setting }}">
                @if ($type === 'choice' && $setting === 'options')
                    <span class="text-sm font-medium">Options</span>
                    <x-lynx-admin::choices-editor model="settings.options" :disabled="! $canEdit" defaultable multiple-model="settings.multiple" />
                    <p class="text-muted-foreground text-sm">Pages store an option's value, and people read its label. Leave a value empty to make it from the label. The default is chosen on new pages and items, and editors can change it.</p>
                @elseif ($definition['type'] === 'boolean')
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                        <input class="blat-checkbox mt-0.5" id="setting-{{ $setting }}" type="checkbox" wire:model="settings.{{ $setting }}">
                        <span class="grid gap-0.5"><span class="font-medium">{{ $words['label'] }}</span>@if ($words['hint'] !== '')<span class="text-muted-foreground">{{ $words['hint'] }}</span>@endif</span>
                    </label>
                @else
                    <label for="setting-{{ $setting }}" class="text-sm font-medium">{{ $words['label'] }}</label>
                    <div class="flex max-w-md items-center gap-2">
                        @if ($words['options'] !== [])
                            <select id="setting-{{ $setting }}" class="blat-select" wire:model="settings.{{ $setting }}">
                                <option value="">Default</option>
                                @foreach ($words['options'] as $value => $word)
                                    <option value="{{ $value }}">{{ $word }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="setting-{{ $setting }}" type="{{ in_array($definition['type'], ['integer', 'number'], true) ? 'number' : 'text' }}" class="blat-input" wire:model="settings.{{ $setting }}" @error('settings.'.$setting) aria-invalid="true" @enderror>
                        @endif
                        @if ($words['unit'] !== '')<span class="text-muted-foreground text-sm">{{ $words['unit'] }}</span>@endif
                    </div>
                    @if ($words['hint'] !== '')<p class="text-muted-foreground text-sm">{{ $words['hint'] }}</p>@endif
                @endif
                @error('settings.'.$setting) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @if ($availableFormatters !== [])
            <div class="grid gap-1.5">
                <span class="text-sm font-medium">When the page is shown</span>
                {{-- The ones on first, in the order they run, by dragging; then the rest. --}}
                <ul class="divide-y overflow-hidden rounded-lg border" @if ($canEdit) wire:sort="sortFormatter" @endif>
                    @foreach ([...$formatters, ...array_keys(array_diff_key($availableFormatters, array_flip($formatters)))] as $formatter)
                        @php($isOn = in_array($formatter, $formatters, true))
                        <li wire:key="formatter-{{ $formatter }}" @if ($isOn) wire:sort:item="{{ $formatter }}" @endif class="flex items-start gap-3 px-3 py-2.5 text-sm" data-formatter="{{ $formatter }}">
                            <input type="checkbox" class="blat-checkbox mt-0.5" id="formatter-{{ $formatter }}" @checked($isOn) wire:click="toggleFormatter({{ Js::from($formatter) }})">
                            <label for="formatter-{{ $formatter }}" class="grid flex-1 cursor-pointer gap-0.5">
                                <span class="font-medium">{{ $labels->formatter($formatter) }}</span>
                                <span class="text-muted-foreground">{{ $availableFormatters[$formatter] ?? 'Not installed.' }}</span>
                            </label>
                            @if ($isOn && count($formatters) > 1 && $canEdit)<span wire:sort:handle class="text-muted-foreground cursor-grab select-none" title="Drag to change the order" aria-hidden="true"><x-lucide-grip-vertical class="size-4" /></span>@endif
                        </li>
                    @endforeach
                </ul>
                <p class="text-muted-foreground text-sm">The stored text never changes: these run as the page is shown, in this order.</p>
                @error('settings.formatters') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
        @endif
    </section>
@endif
