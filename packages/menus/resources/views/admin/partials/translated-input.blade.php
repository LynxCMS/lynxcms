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
        <div role="tabpanel" :id="$id('blat-tabpanel', @js($language['code']))" :aria-labelledby="$id('blat-tab', @js($language['code']))" x-show="tab === @js($language['code'])" @if ($index !== 0) x-cloak @endif>
        <input
            type="text"
            id="{{ $id }}-{{ $language['code'] }}"
            class="blat-input"
            wire:model="{{ $model }}.{{ $language['code'] }}"
            placeholder="{{ $index === 0 ? ($placeholder ?? '') : __('lynx-menus::admin.falls_back', ['language' => $languages[0]['name']]) }}"
            @if ($index === 0) aria-labelledby="{{ $id }}-label" @else aria-label="{{ $label }} ({{ $language['name'] }})" @endif
            @error($error) aria-invalid="true" @enderror
        >
        </div>
    @endforeach
</x-lynx-admin::ui.tabs>
