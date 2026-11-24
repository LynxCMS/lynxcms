{{-- The inbox's pages: which rows show, numbered pages around the current one, and the page size. --}}
<div class="flex flex-wrap items-center justify-between gap-3 text-sm">
    <span class="text-muted-foreground tabular-nums" data-inbox-range>{{ __('lynx-forms::admin.inbox.range', [
        'from' => \LynxCms\Core\Support\LocalizedNumber::format(($page - 1) * $perPage + 1),
        'to' => \LynxCms\Core\Support\LocalizedNumber::format(min($page * $perPage, $total)),
        'total' => \LynxCms\Core\Support\LocalizedNumber::format($total),
    ]) }}</span>
    @if ($lastPage > 1)
        <nav class="flex flex-wrap items-center gap-1" aria-label="{{ __('lynx-forms::admin.inbox.pagination') }}">
            <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">{{ __('lynx-forms::admin.inbox.previous') }}</x-lynx-admin::ui.button>
            @foreach ($pages as $link)
                @if ($link === null)
                    <span class="text-muted-foreground px-1" aria-hidden="true">…</span>
                @else
                    <x-lynx-admin::ui.button :variant="$link === $page ? 'default' : 'outline'" size="xs" type="button" wire:click="goToPage({{ $link }})"
                        :aria-current="$link === $page ? 'page' : null" :aria-label="__('lynx-forms::admin.inbox.page_label', ['page' => $link])" data-page="{{ $link }}">{{ $link }}</x-lynx-admin::ui.button>
                @endif
            @endforeach
            <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">{{ __('lynx-forms::admin.inbox.next') }}</x-lynx-admin::ui.button>
        </nav>
    @endif
    <div class="flex items-center gap-2">
        <label for="inbox-per-page" class="text-muted-foreground">{{ __('lynx-forms::admin.inbox.per_page') }}</label>
        <select id="inbox-per-page" class="blat-select w-20" wire:model.live="perPage">
            @foreach (\LynxCms\Forms\Admin\Livewire\Inbox::PAGE_SIZES as $size)
                <option value="{{ $size }}">{{ $size }}</option>
            @endforeach
        </select>
    </div>
</div>
