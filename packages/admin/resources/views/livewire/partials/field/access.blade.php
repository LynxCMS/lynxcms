{{-- Field access per role (ADR-0151), as ProcessWire's Access tab: managed or not, then a role per row with View and Edit. --}}
<section class="grid gap-4" aria-label="{{ __('lynx-admin::fields.access.heading') }}" data-field-access>
    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
        <input class="blat-checkbox mt-0.5" id="field-manage-access" type="checkbox" wire:model.live="manageAccess" data-manage-access>
        <span class="grid gap-0.5"><span class="font-medium">{{ __('lynx-admin::fields.access.manage') }}</span><span class="text-muted-foreground">{{ __('lynx-admin::fields.access.manage_help') }}</span></span>
    </label>

    @if ($manageAccess)
        <div class="grid gap-2">
            <p class="text-sm font-medium">{{ __('lynx-admin::fields.access.roles') }}</p>
            <p class="lynx-field-help">{{ __('lynx-admin::fields.access.roles_help') }}</p>
            <x-lynx-admin::ui.table variant="card" data-access-table>
                <x-lynx-admin::ui.table-header>
                    <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                        <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::fields.access.role') }}</x-lynx-admin::ui.table-head>
                        <x-lynx-admin::ui.table-head class="w-24 px-4 text-center text-muted-foreground">{{ __('lynx-admin::fields.access.view') }}</x-lynx-admin::ui.table-head>
                        <x-lynx-admin::ui.table-head class="w-24 px-4 text-center text-muted-foreground">{{ __('lynx-admin::fields.access.edit') }}</x-lynx-admin::ui.table-head>
                    </x-lynx-admin::ui.table-row>
                </x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-body>
                    @foreach ($accessRoles as $role)
                        <x-lynx-admin::ui.table-row wire:key="access-{{ $role['name'] }}">
                            <x-lynx-admin::ui.table-cell class="px-4 py-2">{{ $role['label'] }}</x-lynx-admin::ui.table-cell>
                            <x-lynx-admin::ui.table-cell class="px-4 py-2 text-center">
                                <input class="blat-checkbox" type="checkbox" value="{{ $role['name'] }}" wire:model="viewRoles" data-access-view="{{ $role['name'] }}"
                                       aria-label="{{ __('lynx-admin::fields.access.view_role', ['role' => $role['label']]) }}">
                            </x-lynx-admin::ui.table-cell>
                            <x-lynx-admin::ui.table-cell class="px-4 py-2 text-center">
                                <input class="blat-checkbox" type="checkbox" value="{{ $role['name'] }}" wire:model="editRoles" data-access-edit="{{ $role['name'] }}" @disabled(! $role['canEditPages'])
                                       aria-label="{{ __('lynx-admin::fields.access.edit_role', ['role' => $role['label']]) }}" @unless ($role['canEditPages']) title="{{ __('lynx-admin::fields.access.cannot_edit_pages') }}" @endunless>
                            </x-lynx-admin::ui.table-cell>
                        </x-lynx-admin::ui.table-row>
                    @endforeach
                </x-lynx-admin::ui.table-body>
            </x-lynx-admin::ui.table>
            <p class="text-muted-foreground text-sm" data-access-none>{{ __('lynx-admin::fields.access.none') }}</p>
        </div>
    @endif
    @error('settings.access') <p data-error-for="settings.access" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
</section>
