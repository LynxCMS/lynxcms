<x-lynx-admin::auth-layout :title="__('lynx-admin::auth.new_password.title')" :description="__('lynx-admin::auth.new_password.description')">
    <form method="POST" action="{{ route('lynx.admin.account.new-password.save') }}" class="grid gap-5">
        @csrf
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password">{{ __('lynx-admin::auth.new_password.password') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password" name="password" type="password" required autofocus autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            @error('password') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password_confirmation">{{ __('lynx-admin::auth.repeat_password') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
        </div>
        <x-lynx-admin::password-rules for="password" confirm="password_confirmation" />
        <x-lynx-admin::ui.button type="submit" class="w-full">{{ __('lynx-admin::auth.new_password.submit') }}</x-lynx-admin::ui.button>
    </form>
    @if (Route::has('logout'))
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline">{{ __('lynx-admin::shell.sign_out') }}</button>
        </form>
    @endif
</x-lynx-admin::auth-layout>
