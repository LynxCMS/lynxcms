<x-lynx-admin::auth-layout title="Two-factor authentication" description="Enter the code from your authenticator app, or one of your recovery codes.">
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-5" x-data="{ recovery: {{ Js::from($errors->has('recovery_code')) }} }">
        @csrf
        <div class="grid gap-2" x-show="!recovery">
            <x-lynx-admin::ui.label for="code">Authentication code</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" :aria-invalid="$errors->has('code') ? 'true' : null" />
            @error('code') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2" x-show="recovery" x-cloak>
            <x-lynx-admin::ui.label for="recovery_code">Recovery code</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="recovery_code" name="recovery_code" autocomplete="one-time-code" x-bind:disabled="!recovery" :aria-invalid="$errors->has('recovery_code') ? 'true' : null" />
            @error('recovery_code') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">Continue</x-lynx-admin::ui.button>
        <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" x-on:click="recovery = !recovery" x-text="recovery ? 'Use an authentication code' : 'Use a recovery code'">Use a recovery code</button>
    </form>
</x-lynx-admin::auth-layout>
