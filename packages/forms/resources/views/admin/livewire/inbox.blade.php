<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.search') }}</span>
            <input type="search" class="blat-input w-56" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-forms::admin.inbox.search_placeholder') }}">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.show') }}</span>
            <select class="blat-select w-40" wire:model.live="show">
                <option value="open">{{ __('lynx-forms::admin.inbox.open') }}</option>
                <option value="new">{{ __('lynx-forms::admin.inbox.new') }}</option>
                <option value="read">{{ __('lynx-forms::admin.inbox.read') }}</option>
                <option value="archived">{{ __('lynx-forms::admin.inbox.archived') }}</option>
                <option value="spam">{{ __('lynx-forms::admin.inbox.spam') }}</option>
                <option value="all">{{ __('lynx-forms::admin.inbox.all') }}</option>
            </select>
        </label>
        @if (count($forms) > 1)
            <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.form') }}</span>
                <select class="blat-select w-48" wire:model.live="form">
                    <option value="">{{ __('lynx-forms::admin.inbox.all_forms') }}</option>
                    @foreach ($forms as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @foreach ($forms as $key => $label)
            @if ($form === '' || $form === $key)
                <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.forms.export', $key)">{{ __('lynx-forms::admin.inbox.export', ['form' => $label]) }}</x-lynx-admin::ui.button>
            @endif
        @endforeach
    </div>

    @if ($selected !== [])
        <div class="flex flex-wrap items-center gap-2 text-sm" role="toolbar" aria-label="{{ __('lynx-forms::admin.inbox.selected_label') }}">
            <span>{{ __('lynx-forms::admin.inbox.selected', ['count' => count($selected)]) }}</span>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('read')">{{ __('lynx-forms::admin.inbox.mark_read') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('archived')">{{ __('lynx-forms::admin.inbox.archive') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('spam')">{{ __('lynx-forms::admin.inbox.mark_spam') }}</x-lynx-admin::ui.button>
        </div>
    @endif

    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="w-10 px-4"><span class="sr-only">{{ __('lynx-forms::admin.inbox.select') }}</span></x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.received') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.from') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.form') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.status') }}</x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($submissions as $submission)
                <x-lynx-admin::ui.table-row wire:key="submission-{{ $submission->uuid }}" @class(['font-semibold' => $submission->status === 'new'])>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2"><input type="checkbox" class="blat-checkbox" value="{{ $submission->uuid }}" wire:model.live="selected" aria-label="{{ __('lynx-forms::admin.inbox.select') }}"></x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-nowrap px-4 py-2 tabular-nums text-muted-foreground" title="{{ $submission->created_at->toDateTimeString() }}">{{ $submission->created_at->diffForHumans() }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                        <a href="{{ route('lynx.admin.forms.show', $submission->uuid) }}" class="hover:underline">{{ $this->summaryOf($submission) ?: __('lynx-forms::admin.inbox.submission', ['id' => $submission->id]) }}</a>
                        @if ($submission->files->isNotEmpty())
                            <span class="text-muted-foreground text-xs font-normal">· {{ trans_choice('lynx-forms::admin.inbox.files', $submission->files->count()) }}</span>
                        @endif
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-normal text-muted-foreground">{{ $forms[$submission->form] ?? $submission->form }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 font-normal">
                        <x-lynx-admin::ui.badge :variant="$submission->status === 'new' ? 'default' : 'secondary'">{{ __('lynx-forms::admin.status.'.$submission->status) }}</x-lynx-admin::ui.badge>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="5" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">{{ __('lynx-forms::admin.inbox.empty') }}</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>

    @if ($page > 1 || $hasMore)
        <div class="flex items-center gap-2 text-sm">
            <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="$set('page', {{ $page - 1 }})" :disabled="$page <= 1">{{ __('lynx-forms::admin.inbox.newer') }}</x-lynx-admin::ui.button>
            <span class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.pages', ['page' => $page, 'total' => $total]) }}</span>
            <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="$set('page', {{ $page + 1 }})" :disabled="! $hasMore">{{ __('lynx-forms::admin.inbox.older') }}</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
