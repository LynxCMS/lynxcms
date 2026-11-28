{{--
    Presence (BlatUI): a small round status dot with a ring-background outline, optionally pulsing,
    with its label beside it or for screen readers only. Lynx's copy takes its colors from the
    theme's tokens, since the admin has no Tailwind palette, and has no default labels: the caller
    passes wording from its own language file.

      status      online (default) | away | busy | offline
      size        sm | default | lg
      pulse       when true, a ping halo around the dot (off under prefers-reduced-motion)
      label       the status in words, required
      showLabel   when true, the label shows beside the dot; otherwise it is for screen readers
      labelClass  the visible label's text size (text-sm), so it can match a badge (text-xs)

    A11y: the label is always in the page, so the state is never told by color alone.
--}}
@props([
    'status' => 'online',
    'size' => 'default',
    'pulse' => false,
    'label',
    'showLabel' => false,
    'labelClass' => 'text-sm',
])

@once('blat-presence')
    <style>
        @media (prefers-reduced-motion: reduce) {
            [data-slot='presence'] .blat-presence-ping {
                animation: none !important;
            }
        }
    </style>
@endonce

@php
    $pulse = filter_var($pulse, FILTER_VALIDATE_BOOLEAN);
    $showLabel = filter_var($showLabel, FILTER_VALIDATE_BOOLEAN);
    $status = in_array($status, ['online', 'away', 'busy', 'offline'], true) ? $status : 'online';

    // Literal classes, so the Tailwind scanner emits them.
    $colors = [
        'online' => 'bg-success',
        'away' => 'bg-[var(--lynx-notice-warning)]',
        'busy' => 'bg-destructive',
        'offline' => 'bg-muted-foreground/40',
    ];
    $dotColor = $colors[$status];
    $sizes = ['sm' => 'size-2', 'default' => 'size-2.5', 'lg' => 'size-3.5'];
    $dotSize = $sizes[$size] ?? $sizes['default'];
@endphp

<span data-slot="presence" data-status="{{ $status }}" {{ $attributes->twMerge('relative inline-flex items-center gap-1.5') }}>
    <span class="relative inline-flex {{ $dotSize }} shrink-0">
        @if ($pulse)
            <span class="blat-presence-ping absolute inline-flex h-full w-full animate-ping rounded-full {{ $dotColor }} opacity-75" aria-hidden="true"></span>
        @endif
        <span @class(['ring-background relative inline-flex rounded-full ring-2', $dotSize, $dotColor]) aria-hidden="true"></span>
    </span>

    @if ($showLabel)
        <span class="text-foreground {{ $labelClass }}">{{ $label }}</span>
    @else
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
