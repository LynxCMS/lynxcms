<div class="space-y-4">
    <div class="bg-card text-card-foreground grid gap-1 rounded-xl border p-5 shadow-sm">
        <label for="personal-email" class="text-sm font-medium">{{ __('lynx-forms::admin.personal.email') }}</label>
        <input id="personal-email" type="email" class="blat-input max-w-sm" wire:model.live.debounce.400ms="email" autocomplete="off" aria-describedby="personal-email-help">
        <p id="personal-email-help" class="lynx-field-help">{{ __('lynx-forms::admin.personal.email_help') }}</p>
    </div>

    @if ($isSearchable)
        <div class="flex flex-wrap items-center gap-2 text-sm" data-personal-result="{{ $total }}">
            <span>{{ trans_choice('lynx-forms::admin.personal.found', $total, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($total)]) }}</span>
            @if ($total > 0 && $confirmingDelete === null)
                <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.forms.personal-data.export', ['email' => $email])" data-personal-export>{{ __('lynx-forms::admin.personal.export') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="confirmDelete" class="text-destructive hover:text-destructive" data-personal-delete>{{ __('lynx-forms::admin.inbox.delete') }}</x-lynx-admin::ui.button>
            @endif
        </div>

        @if ($confirmingDelete !== null)
            <div class="bg-muted/50 flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm" role="alert" data-confirm-delete>
                <span>{{ __('lynx-forms::admin.inbox.confirm_delete', [
                    'submissions' => trans_choice('lynx-forms::admin.inbox.submissions_count', $confirmingDelete['submissions'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($confirmingDelete['submissions'])]),
                    'files' => trans_choice('lynx-forms::admin.inbox.files', $confirmingDelete['files'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($confirmingDelete['files'])]),
                ]) }}</span>
                <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="$set('confirmingDelete', null)">{{ __('lynx-forms::admin.inbox.cancel') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="destructive" size="xs" type="button" wire:click="delete">{{ trans_choice('lynx-forms::admin.inbox.yes_delete', $confirmingDelete['submissions'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($confirmingDelete['submissions'])]) }}</x-lynx-admin::ui.button>
            </div>
        @endif

        @if ($submissions->isNotEmpty())
            <x-lynx-admin::ui.table variant="card">
                <x-lynx-admin::ui.table-header>
                    <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                        <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.received') }}</x-lynx-admin::ui.table-head>
                        <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.form') }}</x-lynx-admin::ui.table-head>
                        <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.status') }}</x-lynx-admin::ui.table-head>
                        <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">{{ __('lynx-forms::admin.inbox.actions') }}</span></x-lynx-admin::ui.table-head>
                    </x-lynx-admin::ui.table-row>
                </x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-body>
                    @foreach ($submissions as $submission)
                        <x-lynx-admin::ui.table-row wire:key="personal-{{ $submission->uuid }}" data-submission="{{ $submission->id }}">
                            <x-lynx-admin::ui.table-cell class="whitespace-nowrap px-4 py-2 tabular-nums text-muted-foreground" title="{{ $submission->created_at->isoFormat('llll') }}">{{ $submission->created_at->isoFormat('ll') }}</x-lynx-admin::ui.table-cell>
                            <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $forms[$submission->form] ?? $submission->form }}</x-lynx-admin::ui.table-cell>
                            <x-lynx-admin::ui.table-cell class="px-4 py-2"><x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-forms::admin.status.'.$submission->status) }}</x-lynx-admin::ui.badge></x-lynx-admin::ui.table-cell>
                            <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end">
                                <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.forms.show', $submission->uuid)">{{ __('lynx-forms::admin.inbox.view') }}</x-lynx-admin::ui.button>
                            </x-lynx-admin::ui.table-cell>
                        </x-lynx-admin::ui.table-row>
                    @endforeach
                </x-lynx-admin::ui.table-body>
            </x-lynx-admin::ui.table>
            @if ($total > $submissions->count())
                <p class="text-muted-foreground text-sm">{{ __('lynx-forms::admin.personal.more', ['shown' => \LynxCms\Core\Support\LocalizedNumber::format($submissions->count())]) }}</p>
            @endif
        @endif
    @endif
</div>
