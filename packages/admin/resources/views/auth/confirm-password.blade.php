<x-lynx-admin::auth-layout title="Confirm your password" description="This is a protected area. Enter your password to continue.">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="grid gap-5">
        @csrf
        <div class="grid gap-2">
            <x-lynx-admin::ui.label for="password">Password</x-lynx-admin::ui.label>
            <x-lynx-admin::ui.input id="password" name="password" type="password" required autofocus autocomplete="current-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
            @error('password') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <x-lynx-admin::ui.button type="submit" class="w-full">Confirm</x-lynx-admin::ui.button>
    </form>
</x-lynx-admin::auth-layout>
