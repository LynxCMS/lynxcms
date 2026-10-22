<div class="space-y-4">
    @error('roles') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    <label class="grid max-w-sm gap-1 text-sm">
        <span class="text-muted-foreground">Search</span>
        <input id="users-search" type="search" class="blat-input" wire:model.live.debounce.300ms="search" placeholder="Name or email">
    </label>

    <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">User</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Roles</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground"><span class="sr-only">Save</span></x-lynx-admin::ui.table-head>
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
                                    <x-lynx-admin::ui.badge variant="outline" title="Sent {{ $pending[$user['id']] }}">Invitation pending</x-lynx-admin::ui.badge>
                                    <button type="button" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline" wire:click="resendInvitation({{ Js::from($user['id']) }})">Resend invitation</button>
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
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end"><x-lynx-admin::ui.button variant="outline" size="sm" wire:click="saveRoles({{ Js::from($user['id']) }})">Save</x-lynx-admin::ui.button></x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @empty
                    <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="3" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No users match.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
                @endforelse
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>

    @if ($canInvite)
        <form wire:submit="sendInvitation" class="bg-card text-card-foreground grid max-w-2xl gap-4 rounded-xl border p-5 shadow-sm" aria-labelledby="invite-user">
            <div>
                <h2 id="invite-user" class="text-base font-semibold">Invite user</h2>
                <p class="text-muted-foreground text-sm">They get an email with a link to choose their password. There is no sign-up form.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <label for="invite-name" class="text-sm font-medium">Name</label>
                    <input id="invite-name" type="text" class="blat-input" wire:model="invite.name" autocomplete="off" @error('invite.name') aria-invalid="true" @enderror>
                    @error('invite.name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-1">
                    <label for="invite-email" class="text-sm font-medium">Email</label>
                    <input id="invite-email" type="email" class="blat-input" wire:model="invite.email" autocomplete="off" @error('invite.email') aria-invalid="true" @enderror>
                    @error('invite.email') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($roleNames !== [])
                <fieldset class="grid gap-1">
                    <legend class="mb-1 text-sm font-medium">Roles</legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @foreach ($roleNames as $name => $label)
                            <label class="flex items-center gap-1.5"><input class="blat-checkbox" id="invite-role-{{ $name }}" type="checkbox" value="{{ $name }}" wire:model="invite.roles"> {{ $label }}</label>
                        @endforeach
                    </div>
                    @error('invite.roles') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </fieldset>
            @endif
            <div><x-lynx-admin::ui.button type="submit">Send invitation</x-lynx-admin::ui.button></div>
        </form>
    @endif
</div>
