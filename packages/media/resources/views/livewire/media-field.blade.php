{{--
    zoom: the picture a zoom button opened, shown at full size in the field's one dialog. Its id is
    lower case: HTML lowers the dialog's event listener name, and Livewire ids have capitals.
--}}
@php
    $zoomDialog = 'media-zoom-'.strtolower($this->getId());
@endphp
<div class="grid gap-3" x-data="{ zoom: { url: '', name: '' } }">
    @if ($isPerLanguage && count($languages) > 1)
        {{-- A file per language (ADR-0150): a tab per language, with its own files and those every language shares. --}}
        @php
            $own = array_filter($items, fn ($item) => ($item['language'] ?? '') === $language);
            $shared = array_filter($items, fn ($item) => ($item['language'] ?? '') === '');
            $languageLabel = collect($languages)->firstWhere('code', $language)['label'] ?? $language;
        @endphp
        <div class="flex flex-wrap items-center gap-1" role="group" aria-label="{{ __('lynx-media::admin.languages') }}" data-media-languages>
            @foreach ($languages as $tab)
                @php
                    $hasOwn = collect($items)->contains(fn ($item) => ($item['language'] ?? '') === $tab['code']);
                @endphp
                <x-lynx-admin::ui.button size="xs" type="button" :variant="$language === $tab['code'] ? 'secondary' : 'ghost'" wire:click="$set('language', {{ Js::from($tab['code']) }})"
                    aria-pressed="{{ $language === $tab['code'] ? 'true' : 'false' }}" data-media-language="{{ $tab['code'] }}">
                    {{ $tab['label'] }}
                    <span aria-hidden="true" @class(['lynx-tab-state', 'is-filled' => $hasOwn])></span>
                </x-lynx-admin::ui.button>
            @endforeach
        </div>
        <section class="grid gap-2" data-media-group="language" aria-label="{{ __('lynx-media::admin.language_files', ['language' => $languageLabel]) }}">
            <p class="text-sm font-medium">{{ __('lynx-media::admin.language_files', ['language' => $languageLabel]) }}</p>
            @if ($own !== [])
                @include('lynx-media::livewire.partials.media-list', ['shown' => $own])
            @elseif ($fallsBackTo !== null)
                <p class="text-muted-foreground text-sm" data-media-fallback>{{ match (true) { $shared !== [] => __('lynx-media::admin.uses_shared'), $fallsBackTo === '' => __('lynx-media::admin.no_language_file'), default => __('lynx-media::admin.falls_back_to', ['language' => $fallsBackTo]) } }}</p>
            @endif
            @if ($canAdd)
                @include('lynx-media::livewire.partials.media-upload', ['model' => 'uploads'])
            @endif
        </section>
        <section class="grid gap-2 border-t pt-3" data-media-group="shared" aria-label="{{ __('lynx-media::admin.shared_files') }}">
            <p class="text-sm font-medium">{{ __('lynx-media::admin.shared_files') }}</p>
            @if ($shared !== [])
                @include('lynx-media::livewire.partials.media-list', ['shown' => $shared])
            @endif
            @if ($canAddShared)
                @include('lynx-media::livewire.partials.media-upload', ['model' => 'sharedUploads'])
            @endif
        </section>
    @elseif ($items !== [])
        @include('lynx-media::livewire.partials.media-list', ['shown' => $items])
    @endif

    @if ($items !== [])
        @foreach ($items as $index => $item)
            @if ($editing === $item['key'])
                <div class="bg-card grid gap-4 rounded-md border p-4 sm:grid-cols-2" wire:key="details-{{ $item['key'] }}">
                    @if ($isImages)
                        <div class="grid gap-1">
                            <p class="text-sm font-medium">{{ __('lynx-media::admin.focus') }}</p>
                            <p class="text-muted-foreground text-xs">{{ __('lynx-media::admin.focus_help') }}</p>
                            <div class="relative cursor-crosshair overflow-hidden rounded-md"
                                 x-on:click="const r = $el.getBoundingClientRect(); $wire.focus({{ $index }}, (($event.clientX - r.left) / r.width) * 100, (($event.clientY - r.top) / r.height) * 100)">
                                <img src="{{ app(\LynxCms\Media\MediaPaths::class)->url((string) $item['file']) }}" alt="" class="w-full">
                                <span class="pointer-events-none absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary/70 shadow" style="left: {{ $item['focus']['x'] ?? 50 }}%; top: {{ $item['focus']['y'] ?? 50 }}%"></span>
                            </div>
                        </div>
                    @endif
                    <div class="grid content-start gap-3">
                        <div class="grid gap-1">
                            <span class="text-sm font-medium">{{ __('lynx-media::admin.description') }}</span>
                            <x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-1">
                                @if (count($languages) > 1)
                                    <x-lynx-admin::ui.tabs-list variant="compact" aria-label="{{ __('lynx-media::admin.languages') }}">
                                        @foreach ($languages as $language)
                                            <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
                                        @endforeach
                                    </x-lynx-admin::ui.tabs-list>
                                @endif
                                @foreach ($languages as $language)
                                    <div role="tabpanel" :id="$id('blat-tabpanel', @js($language['code']))" :aria-labelledby="$id('blat-tab', @js($language['code']))" x-show="tab === '{{ $language['code'] }}'" @if (! $loop->first) x-cloak @endif>
                                        <textarea rows="2" class="blat-textarea" wire:model="items.{{ $index }}.description.{{ $language['code'] }}" aria-label="{{ __('lynx-media::admin.description_language', ['language' => $language['label']]) }}"></textarea>
                                    </div>
                                @endforeach
                            </x-lynx-admin::ui.tabs>
                        </div>
                        @if ($isPerLanguage)
                            <label class="grid gap-1 text-sm">
                                <span class="font-medium">{{ __('lynx-media::admin.language') }}</span>
                                <select class="blat-select" wire:model.live="items.{{ $index }}.language">
                                    <option value="">{{ __('lynx-media::admin.every_language') }}</option>
                                    @foreach ($languages as $option)
                                        <option value="{{ $option['code'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">{{ __('lynx-media::admin.tags') }}</span>
                            <input type="text" class="blat-input" wire:model="items.{{ $index }}.tags" placeholder="{{ $allowedTags !== [] ? implode(', ', $allowedTags) : __('lynx-media::admin.tags_placeholder') }}">
                        </label>
                    </div>
                </div>
            @endif
        @endforeach
    @endif

    @if ($isImages && $items !== [])
        <x-lynx-admin::ui.dialog :id="$zoomDialog" data-media-viewer>
            <x-lynx-admin::ui.dialog-content class="w-max max-w-[calc(100vw-2rem)] gap-3 p-3 sm:max-w-[calc(100vw-4rem)]">
                <x-lynx-admin::ui.dialog-title class="truncate pe-8 text-sm" x-text="zoom.name" />
                <img :src="zoom.url" :alt="zoom.name" class="max-h-[calc(100vh-8rem)] max-w-full justify-self-center object-contain">
            </x-lynx-admin::ui.dialog-content>
        </x-lynx-admin::ui.dialog>
    @endif

    @if ($canAdd && ! ($isPerLanguage && count($languages) > 1))
        @include('lynx-media::livewire.partials.media-upload', ['model' => 'uploads'])
    @elseif ($pageId === null && ! $disabled)
        <p class="text-muted-foreground text-sm">{{ __($isImages ? 'lynx-media::admin.save_first_images' : 'lynx-media::admin.save_first_files') }}</p>
    @endif
    @error('uploads') <p data-error-for="uploads" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @error('sharedUploads') <p data-error-for="sharedUploads" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
</div>
