@props(['variant' => 'segmented'])   {{-- segmented (default pill) | underline | pills | compact (Lynx) --}}

@php
    $variants = [
        'segmented' => 'bg-muted h-9 w-fit justify-center rounded-lg p-[3px]',
        'underline' => 'w-full justify-start gap-4 border-b',
        'pills' => 'w-fit justify-center gap-1',
        // Lynx: small language switches above each field, about 70% of segmented.
        'compact' => 'bg-muted h-6 w-fit justify-center rounded-md p-0.5',
    ];
@endphp

<div
    data-slot="tabs-list"
    data-variant="{{ $variant }}"
    role="tablist"
    :aria-orientation="orientation"
    :data-orientation="orientation"
    @keydown="$blatNav($event, { orientation, selector: '[role=tab]', loop: true })"
    {{ $attributes->twMerge('group/tabs-list text-muted-foreground inline-flex items-center '.($variants[$variant] ?? $variants['segmented'])) }}
>
    {{ $slot }}
</div>
