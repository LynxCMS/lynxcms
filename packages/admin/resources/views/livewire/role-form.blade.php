<form wire:submit="save" class="flex flex-col gap-6">

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="role-basics">
        <h2 id="role-basics" class="text-base font-semibold">{{ __('lynx-admin::roles.form.heading') }}</h2>
        <div class="grid gap-1">
            <label for="role-name" class="text-sm font-medium">{{ __('lynx-admin::roles.form.name') }}</label>
            @if ($role === null)
                <input id="role-name" type="text" class="blat-input max-w-sm" wire:model="name" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::roles.form.name_help') }}</p>
            @else
                <p id="role-name" class="font-mono text-sm">{{ $name }}</p>
            @endif
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-1">
            <label for="role-label" class="text-sm font-medium">{{ __('lynx-admin::roles.form.label') }}</label>
            <input id="role-label" type="text" class="blat-input max-w-sm" wire:model="label" placeholder="{{ __('lynx-admin::roles.form.label_placeholder') }}">
        </div>
        <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" id="role-superuser" type="checkbox" wire:model.live="superuser"> {{ __('lynx-admin::roles.form.superuser') }}</label>
    </section>

    @unless ($superuser)
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm" aria-labelledby="role-permissions">
            <div class="border-b border-border p-5">
                <h2 id="role-permissions" class="text-base font-semibold">{{ __('lynx-admin::roles.form.permissions') }}</h2>
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::roles.form.permissions_help') }}</p>
            </div>
            @error('grants') <p role="alert" class="text-destructive mt-1 text-sm px-5 pt-3">{{ $message }}</p> @enderror
            <ul class="divide-y divide-border">
                @foreach ($permissions as $key => $permission)
                    <li wire:key="grant-{{ $key }}" class="grid gap-3 px-5 py-3">
                        <label class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                            <input class="blat-checkbox" id="grant-{{ $key }}" type="checkbox" wire:model.live="grants.{{ $key }}.on">
                            <span class="font-mono">{{ $permission['name'] }}</span>
                            <span class="text-muted-foreground">{{ $permission['label'] }}</span>
                        </label>
                        @if ($permission['scoped'] && ($grants[$key]['on'] ?? false))
                            <div class="grid gap-3 ps-6 sm:grid-cols-2">
                                <fieldset class="grid gap-1">
                                    <legend class="mb-1 text-sm font-medium">{{ __('lynx-admin::roles.form.only_templates') }}</legend>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                        @foreach ($templates as $template)
                                            <label class="flex items-center gap-1.5"><input class="blat-checkbox" id="grant-{{ $key }}-template-{{ $template }}" type="checkbox" value="{{ $template }}" wire:model="grants.{{ $key }}.templates"> {{ $template }}</label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <div class="grid gap-1">
                                    <p class="text-sm font-medium">{{ __('lynx-admin::roles.form.only_under') }}</p>
                                    <livewire:lynx-admin.page-picker wire:model="grants.{{ $key }}.branches" :multiple="true" :placeholder="__('lynx-admin::roles.form.search')" :key="'branches-'.$key" />
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endunless

    @foreach ($errors->keys() as $key)
        @if (str_starts_with($key, 'grants.')) <p role="alert" class="text-destructive mt-1 text-sm">{{ $errors->first($key) }}</p> @endif
    @endforeach

    <div class="flex flex-wrap items-center gap-3">
        <x-lynx-admin::ui.button type="submit">{{ __($role === null ? 'lynx-admin::roles.form.create' : 'lynx-admin::roles.form.save') }}</x-lynx-admin::ui.button>
        <x-lynx-admin::ui.button variant="outline" :href="route('lynx.admin.roles')">{{ __('lynx-admin::roles.form.back') }}</x-lynx-admin::ui.button>
        @if ($role !== null && ! $isConfirmingDelete)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="ms-auto text-destructive hover:text-destructive">{{ __('lynx-admin::roles.form.delete') }}</x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($isConfirmingDelete)
        <section role="alert" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5">
            <p class="text-sm">{{ trans_choice('lynx-admin::roles.form.confirm', $deleteUsers, ['name' => $role]) }}</p>
            @error('role') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">{{ __('lynx-admin::roles.form.delete_named', ['name' => $role]) }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">{{ __('lynx-admin::roles.form.cancel') }}</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</form>
