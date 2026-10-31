<form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <section class="bg-card text-card-foreground grid content-start gap-5 rounded-xl border p-5 shadow-sm" aria-labelledby="branding">
        <div class="grid gap-1">
            <h2 id="branding" class="text-base font-semibold">{{ __('lynx-admin::general.branding.heading') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.intro') }}</p>
        </div>

        <div class="grid gap-1">
            <label for="branding-name" class="text-sm font-medium">{{ __('lynx-admin::general.branding.name') }}</label>
            <input id="branding-name" type="text" class="blat-input max-w-sm" wire:model.live.debounce.400ms="name" maxlength="60" placeholder="{{ $defaults->name }}" @error('name') aria-invalid="true" @enderror>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.name_help') }}</p>
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-2">
            <label for="branding-logo" class="text-sm font-medium">{{ __('lynx-admin::general.branding.logo') }}</label>
            <input id="branding-logo" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="blat-input max-w-sm" wire:model="logo" @error('logo') aria-invalid="true" @enderror>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.logo_help') }}</p>
            @error('logo') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            @if ($hasUploadedLogo)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="blat-checkbox" wire:model.live="removeLogo"> {{ __($defaults->logo ? 'lynx-admin::general.branding.remove_logo_configured' : 'lynx-admin::general.branding.remove_logo') }}</label>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['background' => [__('lynx-admin::general.branding.background'), __('lynx-admin::general.branding.background_help')], 'accent' => [__('lynx-admin::general.branding.accent'), __('lynx-admin::general.branding.accent_help')]] as $field => [$label, $help])
                <div class="grid gap-1">
                    <label for="branding-{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" aria-label="{{ __('lynx-admin::general.branding.pick', ['color' => $label]) }}" class="h-9 w-11 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                               x-bind:value="$wire.{{ $field }} || @js($defaults->{$field} ?? '#ffffff')" x-on:change="$wire.set('{{ $field }}', $event.target.value)">
                        <input id="branding-{{ $field }}" type="text" class="blat-input w-32 font-mono" wire:model.live.debounce.400ms="{{ $field }}" placeholder="{{ $defaults->{$field} ?? __('lynx-admin::general.branding.default_placeholder') }}" maxlength="7" @error($field) aria-invalid="true" @enderror>
                        @if ($this->{$field} !== '')
                            <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" wire:click="$set('{{ $field }}', '')">{{ __('lynx-admin::general.branding.default') }}</button>
                        @endif
                    </div>
                    <p class="text-muted-foreground text-sm">{{ $help }}</p>
                    @error($field) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div>
            <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled">{{ __('lynx-admin::general.branding.save') }}</x-lynx-admin::ui.button>
        </div>
    </section>

    {{-- What the sign-in screen will look like, with the values as typed. --}}
    <section aria-label="{{ __('lynx-admin::general.branding.preview_label') }}" class="grid content-start gap-2">
        <p class="text-muted-foreground text-sm font-medium">{{ __('lynx-admin::general.branding.preview') }}</p>
        <div class="bg-muted/40 grid place-items-center gap-4 rounded-xl border px-6 py-10" style="{{ $preview->backgroundStyle() }} {{ $preview->accentStyle() }}">
            <div class="flex items-center gap-2">
                <x-lynx-admin::brand :branding="$preview" :logo-url="$newLogoUrl" size="lg" />
            </div>
            <div class="bg-card text-card-foreground grid w-full gap-3 rounded-xl border p-4 shadow-sm">
                <p class="text-center text-sm font-semibold">{{ __('lynx-admin::auth.login.title') }}</p>
                <div class="bg-muted h-8 rounded-md"></div>
                <div class="bg-muted h-8 rounded-md"></div>
                <span class="bg-primary text-primary-foreground rounded-md py-2 text-center text-sm font-medium">{{ __('lynx-admin::auth.login.title') }}</span>
            </div>
        </div>
        @if ($logo && ! $newLogoUrl)
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.branding.svg_note') }}</p>
        @endif
    </section>
</form>
