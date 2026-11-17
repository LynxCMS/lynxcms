@php
    // Light and dark mode: the logo field, its remove box, and the two colours, each with its translations.
    $modes = [
        'light' => ['heading' => __('lynx-admin::general.branding.light'), 'logo' => 'logo', 'remove' => 'removeLogo', 'hasUploaded' => $hasUploadedLogo, 'colours' => ['background' => 'background', 'accent' => 'accent']],
        'dark' => ['heading' => __('lynx-admin::general.branding.dark'), 'logo' => 'logoDark', 'remove' => 'removeLogoDark', 'hasUploaded' => $hasUploadedDarkLogo, 'colours' => ['backgroundDark' => 'background', 'accentDark' => 'accent']],
    ];
@endphp
<form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <section class="bg-card text-card-foreground grid content-start gap-5 rounded-xl border p-5 shadow-sm" aria-labelledby="branding">
        <div class="grid gap-1">
            <h2 id="branding" class="text-base font-semibold">{{ __('lynx-admin::general.branding.heading') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.intro') }}</p>
        </div>

        <div class="grid gap-1">
            <label for="branding-name" class="text-sm font-medium">{{ __('lynx-admin::general.branding.name') }}</label>
            <input id="branding-name" type="text" class="blat-input max-w-sm" wire:model.live.debounce.400ms="name" maxlength="60" placeholder="{{ $defaults->name }}" @error('name') aria-invalid="true" @enderror>
            <p class="lynx-field-help">{{ __('lynx-admin::general.branding.name_help') }}</p>
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        @foreach ($modes as $mode => $fields)
            <fieldset class="grid gap-4 border-t pt-4">
                <legend class="text-sm font-semibold">{{ $fields['heading'] }}</legend>
                @if ($mode === 'dark')
                    <p class="text-muted-foreground -mt-2 text-sm">{{ __('lynx-admin::general.branding.dark_help') }}</p>
                @endif

                <div class="grid gap-2">
                    <label for="branding-{{ $fields['logo'] }}" class="text-sm font-medium">{{ __('lynx-admin::general.branding.logo') }}</label>
                    <input id="branding-{{ $fields['logo'] }}" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="blat-input max-w-sm" wire:model="{{ $fields['logo'] }}" @error($fields['logo']) aria-invalid="true" @enderror>
                    <p class="lynx-field-help">{{ __($mode === 'dark' ? 'lynx-admin::general.branding.logo_dark_help' : 'lynx-admin::general.branding.logo_help') }}</p>
                    @error($fields['logo']) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                    @if ($fields['hasUploaded'])
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="blat-checkbox" wire:model.live="{{ $fields['remove'] }}"> {{ __($mode === 'dark' ? 'lynx-admin::general.branding.remove_logo_dark' : ($defaults->logo ? 'lynx-admin::general.branding.remove_logo_configured' : 'lynx-admin::general.branding.remove_logo')) }}</label>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields['colours'] as $field => $colour)
                        @php
                            $label = __('lynx-admin::general.branding.'.$colour);
                            // An empty dark colour shows the light one: what dark mode uses until it is set.
                            $fallback = $mode === 'dark' ? ($this->{$colour} ?: $defaults->{$colour}) : $defaults->{$colour};
                        @endphp
                        <div class="grid gap-1">
                            <label for="branding-{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" aria-label="{{ __('lynx-admin::general.branding.pick', ['color' => $label]) }}" class="h-9 w-11 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                       x-bind:value="$wire.{{ $field }} || @js($fallback ?? ($mode === 'dark' ? '#111111' : '#ffffff'))" x-on:change="$wire.set('{{ $field }}', $event.target.value)">
                                <input id="branding-{{ $field }}" type="text" class="blat-input w-32 font-mono" wire:model.live.debounce.400ms="{{ $field }}" placeholder="{{ $fallback ?? __('lynx-admin::general.branding.default_placeholder') }}" maxlength="7" @error($field) aria-invalid="true" @enderror>
                                @if ($this->{$field} !== '')
                                    <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" wire:click="$set('{{ $field }}', '')">{{ __($mode === 'dark' ? 'lynx-admin::general.branding.same_as_light' : 'lynx-admin::general.branding.default') }}</button>
                                @endif
                            </div>
                            <p class="lynx-field-help">{{ __('lynx-admin::general.branding.'.$colour.'_help') }}</p>
                            @error($field) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div>
            <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled">{{ __('lynx-admin::general.branding.save') }}</x-lynx-admin::ui.button>
        </div>
    </section>

    {{-- The sign-in screen in each mode, with the values as typed. The dark box has the .dark class, so it takes BlatUI's dark tokens. --}}
    <section aria-label="{{ __('lynx-admin::general.branding.preview_label') }}" class="grid content-start gap-2">
        <p class="text-muted-foreground text-sm font-medium">{{ __('lynx-admin::general.branding.preview') }}</p>
        @foreach (['light' => false, 'dark' => true] as $mode => $isDark)
            @php($previewLogo = $isDark ? ($newDarkLogoUrl ?? $preview->darkLogoUrl() ?? $newLogoUrl ?? $preview->logoUrl()) : ($newLogoUrl ?? $preview->logoUrl()))
            <div @class(['bg-background text-foreground grid place-items-center gap-4 rounded-xl border px-6 py-8', 'dark' => $isDark]) data-preview="{{ $mode }}" style="{{ $preview->backgroundStyle($isDark) }} {{ $preview->accentStyle($isDark) }}">
                <div class="flex items-center gap-2">
                    <x-lynx-admin::brand :branding="$preview" :logo-url="$previewLogo" :dark-logo-url="false" size="lg" />
                </div>
                <div class="bg-card text-card-foreground grid w-full gap-3 rounded-xl border p-4 shadow-sm">
                    <p class="text-center text-sm font-semibold">{{ __('lynx-admin::auth.login.title') }}</p>
                    <div class="bg-muted h-8 rounded-md"></div>
                    <span class="bg-primary text-primary-foreground rounded-md py-2 text-center text-sm font-medium">{{ __('lynx-admin::auth.login.title') }}</span>
                </div>
            </div>
            <p class="text-muted-foreground -mt-1 mb-2 text-xs">{{ $modes[$mode]['heading'] }}</p>
        @endforeach
        @if ($hasSvgPending)
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.svg_note') }}</p>
        @endif
    </section>
</form>
