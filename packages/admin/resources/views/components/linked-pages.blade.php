{{-- Pages that link to others (LinkedPages rows): each title opens the page in the editor. Used by the Settings tab's "Linked from" panel. --}}
@props(['links', 'empty' => null])
@forelse ($links as $link)
    @if ($loop->first) <ul {{ $attributes->class('divide-y divide-border text-sm') }} data-linked-pages> @endif
    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2" wire:key="linked-page-{{ $link['id'] }}" data-linked-page="{{ $link['id'] }}">
        <a href="{{ route('lynx.admin.pages.edit', $link['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline">{{ $link['title'] }}</a>
        <span class="text-muted-foreground">{{ $link['context'] }}</span>
    </li>
    @if ($loop->last) </ul> @endif
@empty
    @if ($empty !== null)
        <p class="text-muted-foreground text-sm">{{ $empty }}</p>
    @endif
@endforelse
