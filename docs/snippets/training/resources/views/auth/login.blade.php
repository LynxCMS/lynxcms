<x-auth-card :title="__('Sign in')">
    <form method="post" action="{{ route('login') }}" class="grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">Email
            <x-ui.input type="email" name="email" :value="old('email')" required autofocus autocomplete="username" :aria-invalid="$errors->has('email') ? 'true' : null" />
        </label>
        @error('email')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <label class="grid gap-1 text-sm">{{ __('Password') }}
            <x-ui.input type="password" name="password" required autocomplete="current-password" />
        </label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> {{ __('Remember me') }}</label>
        <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">{{ __('Sign in') }}</button>
    </form>
    <p class="atlas-muted text-center text-sm">
        <a href="{{ route('password.request') }}" class="underline">{{ __('Forgot your password?') }}</a>
        · <a href="{{ route('register') }}" class="underline">{{ __('Create an account') }}</a>
    </p>
</x-auth-card>
