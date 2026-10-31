<x-lynx-admin::auth-layout :title="$name ? __('lynx-admin::auth.invitation.welcome_name', ['name' => $name]) : __('lynx-admin::auth.invitation.welcome')" :description="__('lynx-admin::auth.invitation.description')">
    <form method="POST" action="{{ route('lynx.admin.invitation.accept') }}" class="grid gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <div class="grid gap-1">
            <span class="text-sm font-medium">{{ __('lynx-admin::auth.email') }}</span>
            <span class="text-muted-foreground text-sm">{{ $email }}</span>
            @error('email') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password">{{ __('lynx-admin::auth.invitation.password') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password" name="password" type="password" required autofocus autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            @error('password') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password_confirmation">{{ __('lynx-admin::auth.repeat_password') }}</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">{{ __('lynx-admin::auth.invitation.submit') }}</x-lynx-admin::ui.button>
    </form>
</x-lynx-admin::auth-layout>
