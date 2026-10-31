<x-lynx-admin::auth-layout :title="__('lynx-admin::auth.forgot.title')" :description="__('lynx-admin::auth.forgot.description')">
    <form method="POST" action="{{ route('password.email') }}" class="grid gap-5">
        @csrf
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="email">{{ __('lynx-admin::auth.email') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" :aria-invalid="$errors->has('email') ? 'true' : null" />
            @error('email') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">{{ __('lynx-admin::auth.forgot.submit') }}</x-lynx-admin::ui.button>
        <a href="{{ route('login') }}" class="text-muted-foreground hover:text-foreground text-center text-sm underline-offset-4 hover:underline">{{ __('lynx-admin::auth.forgot.back') }}</a>
    </form>
</x-lynx-admin::auth-layout>
