<x-lynx-admin::auth-layout :title="__('lynx-admin::auth.two_factor.title')" :description="__('lynx-admin::auth.two_factor.description')">
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-5" x-data="{ recovery: {{ Js::from($errors->has('recovery_code')) }} }">
        @csrf
        <div class="grid gap-2" x-show="!recovery">
            <x-lynx-admin::ui.label for="code">{{ __('lynx-admin::auth.two_factor.code') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" :aria-invalid="$errors->has('code') ? 'true' : null" />
            @error('code') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2" x-show="recovery" x-cloak>
            <x-lynx-admin::ui.label for="recovery_code">{{ __('lynx-admin::auth.two_factor.recovery_code') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="recovery_code" name="recovery_code" autocomplete="one-time-code" x-bind:disabled="!recovery" :aria-invalid="$errors->has('recovery_code') ? 'true' : null" />
            @error('recovery_code') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">{{ __('lynx-admin::auth.two_factor.submit') }}</x-lynx-admin::ui.button>
        <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" x-on:click="recovery = !recovery" x-text="recovery ? @js(__('lynx-admin::auth.two_factor.use_code')) : @js(__('lynx-admin::auth.two_factor.use_recovery_code'))">{{ __('lynx-admin::auth.two_factor.use_recovery_code') }}</button>
    </form>
</x-lynx-admin::auth-layout>
