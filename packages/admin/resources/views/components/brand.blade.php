@props(['branding' => \LynxCms\Admin\Branding\Branding::current(), 'logoUrl' => null, 'size' => 'sm'])
{{--
    The site's logo, or a mark and its name without one (Branding, ADR-0084). The mark is Lynx's own
    until the site sets a name or a logo (ADR-0089), then the name's initial. size "sm" for the
    sidebar, "lg" for the sign-in screens. logoUrl overrides the branding's, for previews. In the
    collapsed sidebar a logo gives way to the initial: a wide logo would not read at that size. The
    logo or name stays for screen readers there (sr-only), so the link keeps its accessible name.
--}}
@php
    $src = $logoUrl ?? $branding->logoUrl();
    $isLarge = $size === 'lg';
@endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $branding->name }}" @class(['block w-auto object-contain', 'h-12 max-w-60' => $isLarge, 'h-7 max-w-40 group-data-[collapsible=icon]:sr-only' => ! $isLarge])>
    @unless ($isLarge)
        <span class="bg-primary text-primary-foreground hidden size-7 shrink-0 items-center justify-center rounded-md text-sm font-semibold group-data-[collapsible=icon]:flex" aria-hidden="true">{{ $branding->initial() }}</span>
    @endunless
@else
    @if ($branding->usesLynxMark())
        <x-lynx-admin::lynx-mark :class="$isLarge ? 'h-10' : 'h-7'" />
    @else
        <span @class(['bg-primary text-primary-foreground flex shrink-0 items-center justify-center rounded-md font-semibold', 'size-8' => $isLarge, 'size-7 text-sm' => ! $isLarge]) aria-hidden="true">{{ $branding->initial() }}</span>
    @endif
    <span @class(['truncate font-semibold', 'text-lg' => $isLarge, 'group-data-[collapsible=icon]:sr-only' => ! $isLarge])>{{ $branding->name }}</span>
@endif
