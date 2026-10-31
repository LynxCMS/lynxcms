<div class="grid max-w-2xl gap-6">

    <form wire:submit="updatePassword" class="bg-card text-card-foreground grid gap-4 rounded-xl border p-5 shadow-sm" aria-labelledby="account-password">
        <h2 id="account-password" class="text-base font-semibold">{{ __('lynx-admin::account.password.heading') }}</h2>
        <div class="grid gap-1">
            <label for="current-password" class="text-sm font-medium">{{ __('lynx-admin::account.password.current') }}</label>
            <input id="current-password" type="password" class="blat-input max-w-sm" wire:model="currentPassword" autocomplete="current-password" @error('currentPassword') aria-invalid="true" @enderror>
            @error('currentPassword') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-1">
            <label for="new-password" class="text-sm font-medium">{{ __('lynx-admin::account.password.new') }}</label>
            <input id="new-password" type="password" class="blat-input max-w-sm" wire:model="password" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
            @error('password') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-1">
            <label for="new-password-confirmation" class="text-sm font-medium">{{ __('lynx-admin::account.password.repeat') }}</label>
            <input id="new-password-confirmation" type="password" class="blat-input max-w-sm" wire:model="password_confirmation" autocomplete="new-password">
        </div>
        <div><x-lynx-admin::ui.button type="submit">{{ __('lynx-admin::account.password.submit') }}</x-lynx-admin::ui.button></div>
    </form>

    @if ($canUseTwoFactor)
        <section class="bg-card text-card-foreground grid gap-4 rounded-xl border p-5 shadow-sm" aria-labelledby="account-two-factor">
            <div>
                <h2 id="account-two-factor" class="text-base font-semibold">{{ __('lynx-admin::account.two_factor.heading') }}</h2>
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::account.two_factor.description') }}</p>
            </div>

            @if ($qrCodeSvg)
                <div class="grid gap-3">
                    <p class="text-sm">{{ __('lynx-admin::account.two_factor.scan') }}</p>
                    <div class="w-fit rounded-md border bg-white p-2">{!! $qrCodeSvg !!}</div>
                    <p class="text-sm">{{ __('lynx-admin::account.two_factor.setup_key') }} <code class="font-mono select-all">{{ $setupKey }}</code></p>
                    <form wire:submit="confirmTwoFactor" class="flex flex-wrap items-start gap-3">
                        <div class="grid gap-1">
                            <label for="two-factor-code" class="sr-only">{{ __('lynx-admin::account.two_factor.code') }}</label>
                            <input id="two-factor-code" type="text" inputmode="numeric" autocomplete="one-time-code" class="blat-input w-36 font-mono" wire:model="code" placeholder="123456" @error('code') aria-invalid="true" @enderror>
                            @error('code') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                        </div>
                        <x-lynx-admin::ui.button type="submit">{{ __('lynx-admin::account.two_factor.confirm') }}</x-lynx-admin::ui.button>
                        <x-lynx-admin::ui.button type="button" variant="outline" wire:click="disableTwoFactor">{{ __('lynx-admin::account.two_factor.cancel') }}</x-lynx-admin::ui.button>
                    </form>
                </div>
            @elseif ($twoFactorEnabled)
                <p class="text-sm"><x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-admin::account.two_factor.on') }}</x-lynx-admin::ui.badge> {{ __('lynx-admin::account.two_factor.on_description') }}</p>
                @if ($recoveryCodes !== [])
                    <div class="grid gap-2">
                        <h3 class="text-sm font-semibold">{{ __('lynx-admin::account.two_factor.recovery_heading') }}</h3>
                        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::account.two_factor.recovery_description') }}</p>
                        <ul class="bg-muted grid w-fit grid-cols-2 gap-x-6 gap-y-1 rounded-md p-3 font-mono text-sm">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <li>{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="flex flex-wrap gap-3">
                    <x-lynx-admin::ui.button type="button" variant="outline" wire:click="regenerateRecoveryCodes">{{ __('lynx-admin::account.two_factor.new_recovery_codes') }}</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="button" variant="outline" class="text-destructive hover:text-destructive" wire:click="disableTwoFactor">{{ __('lynx-admin::account.two_factor.turn_off') }}</x-lynx-admin::ui.button>
                </div>
            @else
                <div><x-lynx-admin::ui.button type="button" wire:click="enableTwoFactor">{{ __('lynx-admin::account.two_factor.turn_on') }}</x-lynx-admin::ui.button></div>
            @endif
        </section>
    @endif

    @if ($canUsePasskeys)
        <section class="bg-card text-card-foreground grid gap-4 rounded-xl border p-5 shadow-sm" aria-labelledby="account-passkeys">
            <div>
                <h2 id="account-passkeys" class="text-base font-semibold">{{ __('lynx-admin::account.passkeys.heading') }}</h2>
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::account.passkeys.description') }}</p>
            </div>
            @if ($passkeys !== [])
                <ul class="divide-y rounded-md border">
                    @foreach ($passkeys as $passkey)
                        <li class="flex items-center justify-between gap-3 p-3" wire:key="passkey-{{ $passkey['id'] }}">
                            <div class="grid gap-0.5">
                                <span class="text-sm font-medium">{{ $passkey['name'] }} @if ($passkey['authenticator'])<span class="text-muted-foreground font-normal">· {{ $passkey['authenticator'] }}</span>@endif</span>
                                <span class="text-muted-foreground text-xs">{{ __('lynx-admin::account.passkeys.added_on', ['when' => $passkey['added']]) }}@if ($passkey['used']) · {{ __('lynx-admin::account.passkeys.last_used', ['when' => $passkey['used']]) }}@endif</span>
                            </div>
                            <x-lynx-admin::ui.button type="button" variant="outline" size="sm" class="text-destructive hover:text-destructive" wire:click="deletePasskey({{ $passkey['id'] }})" wire:confirm="{{ __('lynx-admin::account.passkeys.confirm_remove', ['name' => $passkey['name']]) }}">{{ __('lynx-admin::account.passkeys.remove') }}</x-lynx-admin::ui.button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::account.passkeys.empty') }}</p>
            @endif
            <div x-data="lynxPasskeyRegister({{ Js::from(['options' => route('passkey.registration-options'), 'submit' => route('passkey.store')]) }}, {{ Js::from(__('lynx-admin::shell.something_wrong')) }})" class="grid gap-2">
                <p x-show="!supported" x-cloak class="text-muted-foreground text-sm">{{ __('lynx-admin::account.passkeys.unsupported') }}</p>
                <form x-show="supported" x-on:submit.prevent="register()" class="flex flex-wrap items-center gap-3">
                    <label for="passkey-name" class="sr-only">{{ __('lynx-admin::account.passkeys.name') }}</label>
                    <input id="passkey-name" type="text" class="blat-input w-56" x-model="name" placeholder="{{ __('lynx-admin::account.passkeys.name') }}" required>
                    <x-lynx-admin::ui.button type="submit" x-bind:disabled="loading">{{ __('lynx-admin::account.passkeys.add') }}</x-lynx-admin::ui.button>
                </form>
                <p x-show="error" x-text="error" x-cloak role="alert" class="text-destructive text-sm"></p>
            </div>
        </section>
    @endif
</div>
