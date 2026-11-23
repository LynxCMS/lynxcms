{{-- The References tab: pages that reference this one, each with links to the fields and items that hold the references. --}}
<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="references-title">
    <h2 id="references-title" class="mb-1 text-base font-semibold">{{ __('lynx-admin::editor.tab_references') }}</h2>
    <p class="text-muted-foreground text-sm mb-4">{{ __('lynx-admin::editor.references.intro') }}</p>
    @forelse ($rows as $row)
        @if ($loop->first) <ul class="divide-y divide-border text-sm" data-linked-pages> @endif
        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2" wire:key="reference-{{ $row['id'] }}" data-referencing-page="{{ $row['id'] }}">
            <span class="min-w-0">
                <a href="{{ route('lynx.admin.pages.edit', $row['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline">{{ $row['title'] }}</a>
                <span class="text-muted-foreground">{{ $row['context'] }}</span>
            </span>
            <span class="flex flex-wrap gap-x-3">
                @foreach ($row['locations'] as $location)
                    <a href="{{ $location['url'] }}" class="text-primary underline-offset-4 hover:underline" data-reference-location>{{ $location['label'] }}</a>
                @endforeach
            </span>
        </li>
        @if ($loop->last) </ul> @endif
    @empty
        <p class="text-muted-foreground text-sm" data-references-empty>{{ __('lynx-admin::editor.references.empty') }}</p>
    @endforelse
    @if ($hasMore)
        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="showMore" class="mt-3" data-references-more>{{ __('lynx-admin::editor.references.more') }}</x-lynx-admin::ui.button>
    @endif
</section>
