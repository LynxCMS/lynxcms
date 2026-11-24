<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="grid gap-1 text-sm">
            <label for="inbox-search" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.search') }}</label>
            <input id="inbox-search" type="search" class="blat-input w-56" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-forms::admin.inbox.search_placeholder') }}">
        </div>
        <div class="grid gap-1 text-sm">
            <label for="inbox-show" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.show') }}</label>
            <select id="inbox-show" class="blat-select w-40" wire:model.live="show">
                @foreach (\LynxCms\Forms\SubmissionFilter::SHOWS as $option)
                    <option value="{{ $option }}">{{ __('lynx-forms::admin.inbox.'.$option) }}</option>
                @endforeach
            </select>
        </div>
        @if (count($forms) > 1)
            <div class="grid gap-1 text-sm">
                <label for="inbox-form" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.form') }}</label>
                <select id="inbox-form" class="blat-select w-48" wire:model.live="form">
                    <option value="">{{ __('lynx-forms::admin.inbox.all_forms') }}</option>
                    @foreach ($forms as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="grid gap-1 text-sm">
            <label for="inbox-period" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.period') }}</label>
            <select id="inbox-period" class="blat-select w-44" wire:model.live="period" data-inbox-period>
                <option value="">{{ __('lynx-forms::admin.inbox.any_time') }}</option>
                @foreach (\LynxCms\Forms\Admin\Livewire\Inbox::PERIODS as $option)
                    <option value="{{ $option }}">{{ __('lynx-forms::admin.inbox.period_'.$option) }}</option>
                @endforeach
            </select>
        </div>
        @if ($period === 'custom')
            <div class="grid gap-1 text-sm">
                <label for="inbox-from" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.date_from') }}</label>
                <input id="inbox-from" type="date" class="blat-input w-40" wire:model.live="from">
            </div>
            <div class="grid gap-1 text-sm">
                <label for="inbox-to" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.date_to') }}</label>
                <input id="inbox-to" type="date" class="blat-input w-40" wire:model.live="to">
            </div>
        @endif
        @foreach ($forms as $key => $label)
            @if ($form === '' || $form === $key)
                <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.forms.export', ['form' => $key, ...$exportQuery])">{{ __('lynx-forms::admin.inbox.export', ['form' => $label]) }}</x-lynx-admin::ui.button>
            @endif
        @endforeach
    </div>

    @if ($selected !== [] || $allMatching)
        @include('lynx-forms::admin.livewire.partials.inbox-selection')
    @endif

    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="w-10 px-4">
                    @if ($submissions->isNotEmpty())
                        <input type="checkbox" class="blat-checkbox" wire:click="togglePage" @checked($isPageSelected)
                               x-init="$el.indeterminate = @js(! $isPageSelected && $selected !== [])" aria-label="{{ __('lynx-forms::admin.inbox.select_page') }}" data-select-page>
                    @endif
                </x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.received') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.from') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.form') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-forms::admin.inbox.status') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">{{ __('lynx-forms::admin.inbox.actions') }}</span></x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($submissions as $submission)
                <x-lynx-admin::ui.table-row wire:key="submission-{{ $submission->uuid }}" data-submission="{{ $submission->id }}" @class(['font-semibold' => $submission->status === 'new'])>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2">
                        @if ($allMatching)
                            <input type="checkbox" class="blat-checkbox" checked disabled aria-label="{{ __('lynx-forms::admin.inbox.select') }}">
                        @else
                            <input type="checkbox" class="blat-checkbox" value="{{ $submission->uuid }}" wire:model.live="selected" aria-label="{{ __('lynx-forms::admin.inbox.select') }}">
                        @endif
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-nowrap px-4 py-2 tabular-nums text-muted-foreground" title="{{ $submission->created_at->isoFormat('llll') }}">{{ $submission->created_at->diffForHumans() }}</x-lynx-admin::ui.table-cell>
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
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end font-normal">
                        <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.forms.show', $submission->uuid)" :aria-label="__('lynx-forms::admin.inbox.view_named', ['id' => $submission->id])">{{ __('lynx-forms::admin.inbox.view') }}</x-lynx-admin::ui.button>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row class="hover:bg-transparent"><x-lynx-admin::ui.table-cell colspan="6" class="whitespace-normal p-4">
                    @if ($hasAny)
                        <x-lynx-admin::empty-state icon="search-x" :title="__('lynx-forms::admin.inbox.empty')" :help="__('lynx-forms::admin.inbox.empty_help')" class="border-0">
                            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="clearFilters">{{ __('lynx-forms::admin.inbox.clear') }}</x-lynx-admin::ui.button>
                        </x-lynx-admin::empty-state>
                    @else
                        <x-lynx-admin::empty-state icon="inbox" :title="__('lynx-forms::admin.inbox.none')" :help="__('lynx-forms::admin.inbox.none_help')" class="border-0" />
                    @endif
                </x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>

    @if ($total > 0)
        @include('lynx-forms::admin.livewire.partials.inbox-pages')
    @endif
</div>
