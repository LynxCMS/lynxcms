{{-- A picture of one block (or item) as the page editor lays it out: each field's label over an empty input, at its width. --}}
<div role="img" aria-label="How a {{ $title }} block looks to editors" class="bg-card min-w-0 rounded-md border text-sm shadow-xs">
    <div class="flex items-center gap-2 border-b px-3 py-2" aria-hidden="true">
        <span class="text-muted-foreground">⠿</span>
        <x-lucide-chevron-down class="text-muted-foreground size-4" />
        <span class="truncate font-medium">{{ $title }}</span>
    </div>
    <div class="grid grid-cols-12 items-start gap-2 p-3" aria-hidden="true">
        @forelse ($rows as $row)
            @php($width = max(1, (int) round(12 * (int) $row['width'] / 100)))
            <div class="grid min-w-0 content-start gap-1" style="grid-column: span {{ $width }} / span {{ $width }}">
                <span class="truncate text-xs font-medium">{{ collect($row['label'])->filter()->first() ?: ($subfieldChoices[$row['name']]['label'] ?? $row['name']) }}@if ($row['required'])<span class="text-destructive"> *</span>@endif</span>
                <span @class(['bg-muted/60 block rounded border', 'h-12' => ($subfieldChoices[$row['name']]['type'] ?? '') === 'textarea', 'h-6' => ($subfieldChoices[$row['name']]['type'] ?? '') !== 'textarea'])></span>
            </div>
        @empty
            <span class="text-muted-foreground col-span-12 text-xs">Fields you add show here.</span>
        @endforelse
    </div>
</div>
