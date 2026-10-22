<div class="space-y-2">
    @if ($chosen !== [])
        <ul class="flex flex-wrap gap-2" aria-label="Chosen pages">
            @foreach ($chosen as $page)
                <li class="bg-secondary text-secondary-foreground inline-flex items-center gap-1 rounded-md border py-0.5 ps-2.5 pe-1 text-sm">
                    <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="font-medium underline-offset-4 hover:underline">{{ $page['title'] }}</a>
                    <button type="button" wire:click="remove({{ $page['id'] }})" class="text-muted-foreground hover:text-destructive flex size-5 items-center justify-center rounded-sm" aria-label="Remove {{ $page['title'] }}"><x-lucide-x class="size-3.5" aria-hidden="true" /></button>
                </li>
            @endforeach
        </ul>
    @endif
    <input type="search" class="blat-input" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder ?? ($multiple || $chosen === [] ? 'Search pages by title to add' : 'Search to replace the chosen page') }}" autocomplete="off" aria-label="Search pages">
    @if ($results !== [])
        <ul class="bg-card text-card-foreground rounded-xl border shadow-sm divide-y divide-border overflow-hidden" aria-label="Matching pages">
            @foreach ($results as $page)
                <li>
                    <button type="button" wire:click="add({{ $page['id'] }})" class="flex w-full items-center justify-between gap-4 px-3 py-2 text-start text-sm hover:bg-accent hover:text-accent-foreground">
                        <span>{{ $page['title'] }}</span>
                        <span class="text-muted-foreground">{{ $page['url'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
