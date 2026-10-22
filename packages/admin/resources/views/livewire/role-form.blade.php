<form wire:submit="save" class="flex flex-col gap-6">

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="role-basics">
        <h2 id="role-basics" class="text-base font-semibold">Role</h2>
        <div class="grid gap-1">
            <label for="role-name" class="text-sm font-medium">Name</label>
            @if ($role === null)
                <input id="role-name" type="text" class="blat-input max-w-sm" wire:model="name" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">Lowercase letters, digits, dashes and underscores, e.g. course-editor.</p>
            @else
                <p id="role-name" class="font-mono text-sm">{{ $name }}</p>
            @endif
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-1">
            <label for="role-label" class="text-sm font-medium">Label</label>
            <input id="role-label" type="text" class="blat-input max-w-sm" wire:model="label" placeholder="From the name">
        </div>
        <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" id="role-superuser" type="checkbox" wire:model.live="superuser"> Superuser: may do everything, including managing roles</label>
    </section>

    @unless ($superuser)
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm" aria-labelledby="role-permissions">
            <div class="border-b border-border p-5">
                <h2 id="role-permissions" class="text-base font-semibold">Permissions</h2>
                <p class="text-muted-foreground text-sm">Page permissions can be limited to templates and to branches of the page tree (a page and everything below it). Nothing chosen means everywhere.</p>
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
                                    <legend class="mb-1 text-sm font-medium">Only these templates</legend>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                        @foreach ($templates as $template)
                                            <label class="flex items-center gap-1.5"><input class="blat-checkbox" id="grant-{{ $key }}-template-{{ $template }}" type="checkbox" value="{{ $template }}" wire:model="grants.{{ $key }}.templates"> {{ $template }}</label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <div class="grid gap-1">
                                    <p class="text-sm font-medium">Only under these pages</p>
                                    <livewire:lynx-admin.page-picker wire:model="grants.{{ $key }}.branches" :multiple="true" placeholder="Search for a page" :key="'branches-'.$key" />
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
        <x-lynx-admin::ui.button type="submit">{{ $role === null ? 'Create role' : 'Save' }}</x-lynx-admin::ui.button>
        <x-lynx-admin::ui.button variant="outline" :href="route('lynx.admin.roles')">Back to roles</x-lynx-admin::ui.button>
        @if ($role !== null && ! $isConfirmingDelete)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="ms-auto text-destructive hover:text-destructive">Delete role</x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($isConfirmingDelete)
        <section role="alert" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5">
            <p class="text-sm">Delete {{ $role }}? {{ $deleteUsers === 1 ? '1 user loses it.' : $deleteUsers.' users lose it.' }}</p>
            @error('role') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">Delete role {{ $role }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">Cancel</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</form>
