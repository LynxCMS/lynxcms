<x-lynx-admin::auth-layout title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="grid gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="email">Email</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="email" name="email" type="email" :value="old('email', $request->string('email')->toString())" required autocomplete="username" :aria-invalid="$errors->has('email') ? 'true' : null" />
            @error('email') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password">New password</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password" name="password" type="password" required autofocus autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            @error('password') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password_confirmation">Repeat the password</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">Save password</x-lynx-admin::ui.button>
    </form>
</x-lynx-admin::auth-layout>
