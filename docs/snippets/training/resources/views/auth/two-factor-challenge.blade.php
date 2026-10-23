{{-- Staff who turned on two-factor authentication in the admin pass here after their password. --}}
<x-auth-card :title="__('Two-factor authentication')">
    <form method="post" action="{{ route('two-factor.login') }}" class="grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">{{ __('Code from your authenticator app') }}
            <x-ui.input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus />
        </label>
        <label class="grid gap-1 text-sm">{{ __('Or a recovery code') }}
            <x-ui.input name="recovery_code" autocomplete="off" />
        </label>
        @error('code')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">{{ __('Sign in') }}</button>
    </form>
</x-auth-card>
