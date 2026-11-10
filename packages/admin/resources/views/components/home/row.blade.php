{{--
    One row of a home screen list: the title links to what it names, details below it ($slot),
    and a column on the end side for when ($aside). Rows are ruled like the page tree's.
--}}
@props(['url', 'title'])
<li class="border-b">
    <a href="{{ $url }}" {{ $attributes->twMerge('group hover:bg-muted/60 focus-visible:ring-ring/50 -mx-2 grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-4 gap-y-0.5 rounded-md px-2 py-2.5 outline-none focus-visible:ring-[3px]') }}>
        <span class="line-clamp-2 text-[0.9375rem] font-medium group-hover:underline group-hover:underline-offset-4">{{ $title }}</span>
        @isset($aside)
            <span class="text-muted-foreground text-end text-xs whitespace-nowrap tabular-nums">{{ $aside }}</span>
        @endisset
        @if ($slot->isNotEmpty())
            <span class="text-muted-foreground col-start-1 min-w-0 text-sm">{{ $slot }}</span>
        @endif
        @isset($asideBelow)
            <span class="text-muted-foreground col-start-2 row-start-2 text-end text-xs whitespace-nowrap">{{ $asideBelow }}</span>
        @endisset
    </a>
</li>
