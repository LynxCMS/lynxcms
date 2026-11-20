<div class="space-y-4">
    @error('roles') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @error('user') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    @if ($issued)
        <div role="status" class="bg-card text-card-foreground grid max-w-2xl gap-3 rounded-xl border p-5 shadow-sm" data-temporary-password x-data="{ copied: false }">
            <p class="font-medium">{{ __($issued['isNew'] ? 'lynx-admin::users.temporary.created' : 'lynx-admin::users.temporary.given', ['name' => $issued['name']]) }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <span class="sr-only">{{ __('lynx-admin::users.temporary.label') }}</span>
                <code x-ref="password" class="bg-muted rounded px-3 py-2 font-mono text-base select-all break-all">{{ $issued['password'] }}</code>
                <x-lynx-admin::ui.button type="button" variant="outline" size="sm" x-bind:disabled="! window.isSecureContext" x-on:click="navigator.clipboard.writeText($refs.password.textContent).then(() => copied = true).catch(() => window.getSelection().selectAllChildren($refs.password))">
                    <span x-show="! copied">{{ __('lynx-admin::users.temporary.copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('lynx-admin::users.temporary.copied') }}</span>
                </x-lynx-admin::ui.button>
            </div>
            {{-- The clipboard API exists only on HTTPS pages (and localhost). --}}
            <p class="text-muted-foreground text-sm" x-show="! window.isSecureContext" x-cloak>{{ __('lynx-admin::users.temporary.copy_needs_https') }}</p>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::users.temporary.shown_once', ['when' => $issued['until']]) }}</p>
            <div><x-lynx-admin::ui.button type="button" size="sm" wire:click="dismissIssued">{{ __('lynx-admin::users.temporary.done') }}</x-lynx-admin::ui.button></div>
        </div>
    @endif

    <label class="grid max-w-sm gap-1 text-sm">
        <span class="text-muted-foreground">{{ __('lynx-admin::users.list.search') }}</span>
        <input id="users-search" type="search" class="blat-input" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-admin::users.list.search_placeholder') }}">
    </label>

    <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::users.list.user') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::users.list.roles') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground"><span class="sr-only">{{ __('lynx-admin::users.list.save') }}</span></x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @forelse ($users as $user)
                    <x-lynx-admin::ui.table-row wire:key="user-{{ $user['id'] }}">
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            <span class="font-medium">{{ $user['label'] }}</span>
                            @if ($user['detail'] !== '') <span class="block text-muted-foreground">{{ $user['detail'] }}</span> @endif
                            @isset($pending[$user['id']])
                                <span class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-lynx-admin::ui.badge variant="outline" title="{{ __('lynx-admin::users.list.sent', ['when' => $pending[$user['id']]]) }}">{{ __('lynx-admin::users.list.pending') }}</x-lynx-admin::ui.badge>
                                    @if ($sendsMail)
                                        <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" wire:click="resendInvitation({{ Js::from($user['id']) }})">{{ __('lynx-admin::users.list.resend') }}</button>
                                    @endif
                                </span>
                            @endisset
                            @if (in_array($user['id'], $withoutTwoFactor, true))
                                <span class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-lynx-admin::ui.badge variant="outline" data-no-two-factor="{{ $user['id'] }}" title="{{ __('lynx-admin::users.list.no_two_factor_help') }}">{{ __('lynx-admin::users.list.no_two_factor') }}</x-lynx-admin::ui.badge>
                                </span>
                            @endif
                            @isset($temporary[$user['id']])
                                <span class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-lynx-admin::ui.badge variant="outline" title="{{ __('lynx-admin::users.list.temporary_until', ['when' => $temporary[$user['id']]]) }}">{{ __('lynx-admin::users.list.temporary') }}</x-lynx-admin::ui.badge>
                                </span>
                            @endisset
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($roleNames as $name => $label)
                                    <label class="flex items-center gap-1.5"><input class="blat-checkbox" id="user-{{ $user['id'] }}-role-{{ $name }}" type="checkbox" value="{{ $name }}" wire:model="roles.{{ $user['id'] }}"> {{ $label }}</label>
                                @endforeach
                            </div>
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end">
                            <div class="flex flex-wrap justify-end gap-2">
                                <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="saveRoles({{ Js::from($user['id']) }})">{{ __('lynx-admin::users.list.save') }}</x-lynx-admin::ui.button>
                                @if (in_array($user['id'], $twoFactor, true))
                                    <x-lynx-admin::ui.button variant="ghost" size="sm" wire:click="resetTwoFactor({{ Js::from($user['id']) }})" wire:confirm="{{ __('lynx-admin::users.two_factor.confirm_reset', ['name' => $user['label']]) }}">{{ __('lynx-admin::users.two_factor.reset') }}</x-lynx-admin::ui.button>
                                @endif
                                @if ($canInvite && $user['id'] !== $me)
                                    <x-lynx-admin::ui.button variant="ghost" size="sm" wire:click="giveTemporaryPassword({{ Js::from($user['id']) }})" wire:confirm="{{ __('lynx-admin::users.list.confirm_new_password', ['name' => $user['label']]) }}">{{ __('lynx-admin::users.list.new_password') }}</x-lynx-admin::ui.button>
                                @endif
                            </div>
                        </x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @empty
                    <x-lynx-admin::ui.table-row class="hover:bg-transparent"><x-lynx-admin::ui.table-cell colspan="3" class="whitespace-normal p-4">
                        <x-lynx-admin::empty-state icon="search-x" :title="__('lynx-admin::users.list.empty')" :help="__('lynx-admin::users.list.empty_help')" class="border-0" />
                    </x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
                @endforelse
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>

    @if ($canInvite)
        <form wire:submit="addUser" class="bg-card text-card-foreground grid max-w-2xl gap-4 rounded-xl border p-5 shadow-sm" aria-labelledby="invite-user">
            <div>
                <h2 id="invite-user" class="text-base font-semibold">{{ __('lynx-admin::users.invite.heading_add') }}</h2>
                <p class="text-muted-foreground text-sm">{{ __($sendsMail ? 'lynx-admin::users.invite.help' : 'lynx-admin::users.invite.help_no_mail') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <label for="invite-name" class="text-sm font-medium">{{ __('lynx-admin::users.invite.name') }}</label>
                    <input id="invite-name" type="text" class="blat-input" wire:model="invite.name" autocomplete="off" @error('invite.name') aria-invalid="true" @enderror>
                    @error('invite.name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-1">
                    <label for="invite-email" class="text-sm font-medium">{{ __('lynx-admin::users.invite.email') }}</label>
                    <input id="invite-email" type="email" class="blat-input" wire:model="invite.email" autocomplete="off" @error('invite.email') aria-invalid="true" @enderror>
                    @error('invite.email') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($roleNames !== [])
                <fieldset class="grid gap-1">
                    <legend class="mb-1 text-sm font-medium">{{ __('lynx-admin::users.invite.roles') }}</legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @foreach ($roleNames as $name => $label)
                            <label class="flex items-center gap-1.5"><input class="blat-checkbox" id="invite-role-{{ $name }}" type="checkbox" value="{{ $name }}" wire:model="invite.roles"> {{ $label }}</label>
                        @endforeach
                    </div>
                    @error('invite.roles') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </fieldset>
            @endif
            @if ($sendsMail)
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">{{ __('lynx-admin::users.invite.method') }}</legend>
                    @foreach (['email' => 'by_email', 'password' => 'by_password'] as $method => $key)
                        <label class="flex items-start gap-2 text-sm">
                            <input class="blat-radio mt-0.5" id="invite-method-{{ $method }}" type="radio" name="invite-method" value="{{ $method }}" wire:model.live="invite.method">
                            <span><span class="font-medium">{{ __('lynx-admin::users.invite.'.$key) }}</span> <span class="text-muted-foreground block">{{ __('lynx-admin::users.invite.'.$key.'_help') }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
            @endif
            <div><x-lynx-admin::ui.button type="submit">{{ __($sendsMail && $invite['method'] === 'email' ? 'lynx-admin::users.invite.send' : 'lynx-admin::users.invite.create') }}</x-lynx-admin::ui.button></div>
        </form>
    @endif
</div>
