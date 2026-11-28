<div class="grid gap-6" data-backups @if ($isBusy) wire:poll.5s @endif>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <dl class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-1 text-sm" data-backup-summary>
            <div class="flex gap-1"><dt>{{ __('lynx-backup::admin.schedule') }}:</dt><dd class="text-foreground">{{ $summary['schedule'] }}</dd></div>
            <div class="flex gap-1"><dt>{{ __('lynx-backup::admin.disks') }}:</dt><dd class="text-foreground font-mono">{{ implode(', ', $summary['disks']) }}</dd></div>
            <div class="flex gap-1"><dt>{{ __('lynx-backup::admin.encryption') }}:</dt><dd class="text-foreground">{{ __($summary['encrypted'] ? 'lynx-backup::admin.encrypted' : 'lynx-backup::admin.not_encrypted') }}</dd></div>
        </dl>
        <x-lynx-admin::ui.button type="button" wire:click="backUpNow" wire:loading.attr="disabled" data-back-up-now>{{ __('lynx-backup::admin.back_up_now') }}</x-lynx-admin::ui.button>
    </div>

    @error('backup') <p role="alert" class="text-destructive text-sm" data-error-for="backup">{{ $message }}</p> @enderror

    @if ($rows === [])
        <p class="text-muted-foreground text-sm" data-backups-empty>{{ __('lynx-backup::admin.empty') }}</p>
    @else
        <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-backup::admin.made') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-backup::admin.status_heading') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-backup::admin.reason') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-backup::admin.asked_by') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">{{ __('lynx-backup::admin.size') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">{{ __('lynx-backup::admin.actions') }}</span></x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @foreach ($rows as $row)
                    <x-lynx-admin::ui.table-row wire:key="backup-{{ $row['id'] }}" data-backup="{{ $row['id'] }}">
                        <x-lynx-admin::ui.table-cell class="px-4 py-2"><time datetime="{{ $row['datetime'] }}">{{ $row['when'] }}</time></x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2" data-backup-status="{{ $row['status'] }}">
                            @if ($row['busy'])
                                <x-lynx-admin::ui.presence status="online" size="sm" pulse :label="__('lynx-backup::admin.status.'.$row['status'])" show-label label-class="text-xs" />
                            @else
                                <x-lynx-admin::ui.badge :variant="match ($row['status']) { 'failed' => 'destructive', 'done' => 'default', default => 'secondary' }">{{ __('lynx-backup::admin.status.'.$row['status']) }}</x-lynx-admin::ui.badge>
                            @endif
                            @if ($row['files']) <x-lynx-admin::ui.badge variant="outline" class="ms-1">{{ __('lynx-backup::admin.with_files') }}</x-lynx-admin::ui.badge> @endif
                            @if ($row['error'] !== null)
                                <p class="text-destructive mt-1 max-w-md whitespace-normal text-xs">{{ \Illuminate\Support\Str::limit($row['error'], 200) }}</p>
                            @endif
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $row['reason'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2">{{ $row['actor'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end tabular-nums">{{ $row['size'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end">
                            @if ($row['status'] === 'done')
                                <x-lynx-admin::ui.button variant="ghost" size="sm" :href="route('lynx.admin.backups.download', $row['id'])" data-backup-download>{{ __('lynx-backup::admin.download') }}</x-lynx-admin::ui.button>
                            @endif
                            @unless ($row['busy'])
                                <x-lynx-admin::ui.button variant="ghost" size="sm" wire:click="delete({{ $row['id'] }})" wire:confirm="{{ __('lynx-backup::admin.confirm_delete') }}" data-backup-delete>{{ __('lynx-backup::admin.delete') }}</x-lynx-admin::ui.button>
                            @endunless
                        </x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @endforeach
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
    @endif

    <x-lynx-admin::section id="backup-settings" :title="__('lynx-backup::admin.settings')" remember="backup:settings" body-class="p-5" data-backup-settings>
        <form wire:submit="saveSettings" class="grid gap-5">
            <div class="grid gap-1">
                <div class="flex items-center gap-3">
                    <x-lynx-admin::ui.switch id="backup-include-files" wire:model="includeFiles" :checked="$includeFiles" aria-describedby="backup-include-files-help" />
                    <label for="backup-include-files" class="text-sm font-medium">{{ __('lynx-backup::admin.include_files') }}</label>
                </div>
                <p id="backup-include-files-help" class="lynx-field-help">{{ __('lynx-backup::admin.include_files_help') }}</p>
            </div>
            @if ($offersDump)
                <div class="grid gap-1" data-sqlite-format>
                    <label for="backup-sqlite-format" class="text-sm font-medium">{{ __('lynx-backup::admin.sqlite_format') }}</label>
                    <select id="backup-sqlite-format" class="blat-select max-w-sm" wire:model="sqliteFormat" aria-describedby="backup-sqlite-format-help">
                        <option value="copy">{{ __('lynx-backup::admin.format.copy') }}</option>
                        <option value="dump">{{ __('lynx-backup::admin.format.dump') }}</option>
                    </select>
                    <p id="backup-sqlite-format-help" class="lynx-field-help">{{ __('lynx-backup::admin.sqlite_format_help') }}</p>
                    @error('sqlite_format') <p data-error-for="sqlite_format" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <x-lynx-admin::ui.button type="submit" variant="outline">{{ __('lynx-backup::admin.save_settings') }}</x-lynx-admin::ui.button>
            </div>
        </form>
    </x-lynx-admin::section>
</div>
