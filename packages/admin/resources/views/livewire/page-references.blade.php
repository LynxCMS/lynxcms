{{-- Settings > Page references (PageReferencesScreen): one report with its rows and paging, then the index's health for page_references.manage. --}}
@php($perPage = \LynxCms\Admin\Livewire\PageReferencesScreen::PER_PAGE)
<div class="grid grid-cols-1 gap-6" data-page-references>
    <x-lynx-admin::ui.tabs :value="$report" wire:key="reports-{{ $report }}">
        <x-lynx-admin::ui.tabs-list variant="underline" aria-label="{{ __('lynx-admin::page_references.reports') }}">
            @foreach (\LynxCms\Admin\Livewire\PageReferencesScreen::REPORTS as $key)
                <x-lynx-admin::ui.tabs-trigger :value="$key" :panel="false" wire:click="show('{{ $key }}')" data-report-tab="{{ $key }}">{{ __('lynx-admin::page_references.'.$key.'.title') }}</x-lynx-admin::ui.tabs-trigger>
            @endforeach
        </x-lynx-admin::ui.tabs-list>
    </x-lynx-admin::ui.tabs>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="report-title" data-report="{{ $report }}">
        <h2 id="report-title" class="mb-1 text-base font-semibold">{{ __('lynx-admin::page_references.'.$report.'.title') }}</h2>
        <p class="text-muted-foreground text-sm mb-4">{{ __('lynx-admin::page_references.'.$report.'.intro') }}</p>
        @forelse ($rows as $row)
            @if ($loop->first) <ul class="divide-y divide-border text-sm"> @endif
            <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2" wire:key="report-{{ $report }}-{{ $loop->index }}-{{ $row['id'] }}" data-report-page="{{ $row['id'] }}">
                <span class="min-w-0">
                    <a href="{{ route('lynx.admin.pages.edit', $row['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline">{{ $row['title'] }}</a>
                    <span class="text-muted-foreground">{{ $row['context'] }}</span>
                    @if ($row['detail'] !== '')
                        <span class="block" data-report-detail>{{ $row['detail'] }}</span>
                    @endif
                </span>
                <span class="flex flex-wrap gap-x-3">
                    @foreach ($row['links'] as $link)
                        <a href="{{ $link['url'] }}" class="text-primary underline-offset-4 hover:underline" data-reference-location>{{ $link['label'] }}</a>
                    @endforeach
                </span>
            </li>
            @if ($loop->last) </ul> @endif
        @empty
            <p class="text-muted-foreground text-sm" data-report-empty>{{ __('lynx-admin::page_references.'.$report.'.empty') }}</p>
        @endforelse

        @if ($report !== 'linked' && $total > $perPage)
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm" data-report-paging>
                <span class="text-muted-foreground">{{ __('lynx-admin::page_references.paging.range', ['from' => \LynxCms\Core\Support\LocalizedNumber::format($start + 1), 'to' => \LynxCms\Core\Support\LocalizedNumber::format(min($start + $perPage, $total)), 'total' => \LynxCms\Core\Support\LocalizedNumber::format($total)]) }}</span>
                <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="previous" :disabled="$start === 0" data-report-previous>{{ __('lynx-admin::page_references.paging.previous') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="next" :disabled="$start + $perPage >= $total" data-report-next>{{ __('lynx-admin::page_references.paging.next') }}</x-lynx-admin::ui.button>
            </div>
        @endif
    </section>

    @if ($canManage)
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="index-health" data-index-health>
            <h2 id="index-health" class="mb-1 text-base font-semibold">{{ __('lynx-admin::page_references.health.title') }}</h2>
            <p class="text-muted-foreground text-sm mb-4">{{ __('lynx-admin::page_references.health.intro') }}</p>
            @if ($health !== null)
                <p class="text-sm mb-4" role="status" data-index-check="{{ $health['pages'] === 0 ? 'current' : 'differs' }}">
                    {{ $health['pages'] === 0
                        ? __('lynx-admin::page_references.health.current')
                        : trans_choice('lynx-admin::page_references.health.differs', $health['pages'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($health['pages']), 'added' => \LynxCms\Core\Support\LocalizedNumber::format($health['added']), 'removed' => \LynxCms\Core\Support\LocalizedNumber::format($health['removed']), 'resorted' => \LynxCms\Core\Support\LocalizedNumber::format($health['resorted'])]) }}
                </p>
            @endif
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="checkIndex" data-check-index>{{ __('lynx-admin::page_references.health.check') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button type="button" wire:click="rebuild" data-rebuild-index>{{ __('lynx-admin::page_references.health.rebuild') }}</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</div>
