{{-- A page password: hidden until shown, so editors can read it to send it to guests; Generate fills a random one. --}}
<div class="flex gap-2" x-data="{ shown: false }">
    <input
        x-bind:type="shown ? 'text' : 'password'"
        type="password"
        id="input-{{ $field->name }}-{{ $code }}"
        class="blat-input font-mono"
        wire:model="{{ $model }}"
        autocomplete="new-password"
        placeholder="{{ __('lynx-page-password::admin.placeholder') }}"
        aria-labelledby="field-{{ $field->name }}"
        @disabled($disabled)
        @error('values.'.$field->name) aria-invalid="true" @enderror
    >
    <x-lynx-admin::ui.button variant="outline" size="sm" type="button" x-on:click="shown = ! shown" x-text="shown ? @js(__('lynx-page-password::admin.hide')) : @js(__('lynx-page-password::admin.show'))">{{ __('lynx-page-password::admin.show') }}</x-lynx-admin::ui.button>
    @unless ($disabled)
        <x-lynx-admin::ui.button variant="outline" size="sm" type="button"
            x-on:click="const words = new Uint32Array(3); crypto.getRandomValues(words); $wire.set(@js($model), Array.from(words, (n) => n.toString(36)).join('-')); shown = true">{{ __('lynx-page-password::admin.generate') }}</x-lynx-admin::ui.button>
    @endunless
</div>
