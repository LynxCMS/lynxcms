{{--
    What a list shows when it has nothing to show: an icon, what would be here, how it gets here
    ($help) and, in the slot, the action that adds the first one. "compact" fits a panel or a
    narrow column; the full size fills a screen's list or a table.
--}}
@props(['icon' => 'inbox', 'title', 'help' => null, 'compact' => false])
<div {{ $attributes->twMerge($compact
    ? 'flex items-start gap-3 rounded-lg border border-dashed p-4 text-start'
    : 'flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-10 text-center text-balance') }} data-empty-state>
    <span class="bg-muted text-muted-foreground flex {{ $compact ? 'size-8' : 'size-10' }} shrink-0 items-center justify-center rounded-md" aria-hidden="true">
        <x-lynx-admin::ui.icon :name="$icon" class="{{ $compact ? 'size-4' : 'size-5' }}" />
    </span>
    <div class="grid gap-1 text-sm {{ $compact ? '' : 'max-w-md justify-items-center' }}">
        <p class="text-foreground font-medium">{{ $title }}</p>
        @if (filled($help))
            <p class="text-muted-foreground">{{ $help }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-2 {{ $compact ? '' : 'justify-center' }}">{{ $slot }}</div>
        @endif
    </div>
</div>
