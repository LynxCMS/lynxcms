<x-lynx-admin::section id="forms-turnstile" :title="__('lynx-forms::admin.turnstile.heading')" remember="forms:turnstile" collapsed
    :has-error="$errors->has('site_key') || $errors->has('secret')" body-class="p-5" data-turnstile-settings>
    <x-slot:meta>
        <x-lynx-admin::ui.badge :variant="$isOn ? 'default' : 'secondary'" data-turnstile-state="{{ $isOn ? 'on' : 'off' }}">{{ __($isOn ? 'lynx-forms::admin.turnstile.on' : 'lynx-forms::admin.turnstile.off_badge') }}</x-lynx-admin::ui.badge>
    </x-slot:meta>
    <div class="grid gap-5">
        <p class="text-muted-foreground text-sm">{{ __('lynx-forms::admin.turnstile.help') }}</p>
        @if ($isFromEnv)
            <p class="text-sm" data-turnstile-env>{{ __('lynx-forms::admin.turnstile.from_env') }}</p>
        @else
            <form wire:submit="save" class="grid gap-5">
                <div class="grid gap-1">
                    <label for="turnstile-site-key" class="text-sm font-medium">{{ __('lynx-forms::admin.turnstile.site_key') }}</label>
                    <input id="turnstile-site-key" type="text" class="blat-input max-w-sm font-mono" wire:model="siteKey" autocomplete="off" @error('site_key') aria-invalid="true" @enderror>
                    @error('site_key') <p data-error-for="site_key" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-1">
                    <label for="turnstile-secret" class="text-sm font-medium">{{ __('lynx-forms::admin.turnstile.secret') }}</label>
                    <input id="turnstile-secret" type="password" class="blat-input max-w-sm font-mono" wire:model="secret" autocomplete="new-password" aria-describedby="turnstile-secret-help">
                    <p id="turnstile-secret-help" class="lynx-field-help">{{ __($isOn ? 'lynx-forms::admin.turnstile.secret_saved' : 'lynx-forms::admin.turnstile.secret_help') }}</p>
                    @error('secret') <p data-error-for="secret" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-lynx-admin::ui.button type="submit">{{ __('lynx-forms::admin.turnstile.save') }}</x-lynx-admin::ui.button>
                    @if ($isOn)
                        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="turnOff" data-turnstile-off>{{ __('lynx-forms::admin.turnstile.turn_off') }}</x-lynx-admin::ui.button>
                    @endif
                </div>
            </form>
        @endif
    </div>
</x-lynx-admin::section>
