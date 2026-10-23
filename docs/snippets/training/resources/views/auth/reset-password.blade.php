<x-auth-card :title="__('Choose a new password')">
    <form method="post" action="{{ route('password.update') }}" class="grid gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label class="grid gap-1 text-sm">Email
            <x-ui.input type="email" name="email" :value="old('email', $request->email)" required autocomplete="username" />
        </label>
        <label class="grid gap-1 text-sm">{{ __('Password') }}
            <x-ui.input type="password" name="password" required autocomplete="new-password" />
        </label>
        @error('password')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <label class="grid gap-1 text-sm">{{ __('Confirm password') }}
            <x-ui.input type="password" name="password_confirmation" required autocomplete="new-password" />
        </label>
        <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">{{ __('Save password') }}</button>
    </form>
</x-auth-card>
