<x-auth-card :title="__('Forgot your password?')">
    <form method="post" action="{{ route('password.email') }}" class="grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">Email
            <x-ui.input type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        </label>
        @error('email')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">{{ __('Email me a reset link') }}</button>
    </form>
</x-auth-card>
