<x-auth-card :title="__('Create an account')">
    <form method="post" action="{{ route('register') }}" class="grid gap-4">
        @csrf
        <label class="grid gap-1 text-sm">{{ __('Name') }}
            <x-ui.input name="name" :value="old('name')" required autofocus autocomplete="name" />
        </label>
        @error('name')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <label class="grid gap-1 text-sm">Email
            <x-ui.input type="email" name="email" :value="old('email')" required autocomplete="username" />
        </label>
        @error('email')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <label class="grid gap-1 text-sm">{{ __('Password') }}
            <x-ui.input type="password" name="password" required autocomplete="new-password" />
        </label>
        @error('password')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <label class="grid gap-1 text-sm">{{ __('Confirm password') }}
            <x-ui.input type="password" name="password_confirmation" required autocomplete="new-password" />
        </label>
        <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">{{ __('Create an account') }}</button>
    </form>
    <p class="atlas-muted text-center text-sm"><a href="{{ route('login') }}" class="underline">{{ __('I already have an account') }}</a></p>
</x-auth-card>
