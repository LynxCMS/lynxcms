{{-- One text input per language behind tabs. $model is the Livewire path, e.g. "item.label". --}}
<x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-0">
    @if (count($languages) > 1)
        <x-lynx-admin::ui.tabs-list variant="compact" class="mb-2">
            @foreach ($languages as $language)
                <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
            @endforeach
        </x-lynx-admin::ui.tabs-list>
    @endif
    @foreach ($languages as $index => $language)
        <input
            type="text"
            id="{{ $id }}-{{ $language['code'] }}"
            class="blat-input"
            wire:model="{{ $model }}.{{ $language['code'] }}"
            x-show="tab === '{{ $language['code'] }}'"
            placeholder="{{ $index === 0 ? ($placeholder ?? '') : 'Falls back to '.$languages[0]['name'] }}"
            @if ($index === 0) aria-labelledby="{{ $id }}-label" @else aria-label="{{ $label }} ({{ $language['name'] }})" @endif
            @error($error) aria-invalid="true" @enderror
        >
    @endforeach
</x-lynx-admin::ui.tabs>
