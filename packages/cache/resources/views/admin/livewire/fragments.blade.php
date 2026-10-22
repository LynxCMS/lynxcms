<section aria-labelledby="cache-fragments" class="bg-card grid max-w-4xl gap-3 rounded-xl border p-5">
    <h2 id="cache-fragments" class="font-medium">Fragments</h2>
    <p class="text-muted-foreground text-sm">Parts of pages kept by <code class="font-mono">@@lynxCache</code> and <code class="font-mono">lynx()->cache()</code>. {{ $total }} stored {{ $total === 1 ? 'fragment' : 'fragments' }}.</p>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" class="blat-input w-56" wire:model.live.debounce.300ms="search" placeholder="Filter by name" aria-label="Filter fragments by name">
        <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearAll">Clear every fragment</x-lynx-admin::ui.button>
        @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
    </div>

    @if ($entries->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-muted-foreground">
                    <tr>
                        <th scope="col" class="py-2 pr-3 font-medium">Name</th>
                        <th scope="col" class="py-2 pr-3 font-medium">Language</th>
                        <th scope="col" class="py-2 pr-3 font-medium">Cleared by</th>
                        <th scope="col" class="py-2 pr-3 font-medium">Size</th>
                        <th scope="col" class="py-2 pr-3 font-medium">Stored</th>
                        <th scope="col" class="py-2 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr class="border-t" wire:key="fragment-{{ $entry->id }}">
                            <td class="py-2 pr-3 font-mono whitespace-nowrap">{{ $entry->name }}@if ($entry->audience !== 'guest') <span class="text-muted-foreground" title="Guests with unlocked pages">*</span>@endif</td>
                            <td class="py-2 pr-3">{{ $entry->language }}</td>
                            <td class="py-2 pr-3 font-mono text-xs">{{ implode(', ', array_map(fn ($dependency) => $dependency === 'any' ? 'any change' : $dependency, $entry->dependencies())) }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ number_format($entry->size / 1024, 1) }} KB</td>
                            <td class="py-2 pr-3" title="{{ $entry->created_at }}">{{ $entry->created_at->diffForHumans() }}@if ($entry->expires_at), until {{ $entry->expires_at->diffForHumans() }}@endif</td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="view({{ $entry->id }})" :aria-expanded="$viewing === $entry->id ? 'true' : 'false'">{{ $viewing === $entry->id ? 'Hide' : 'View' }}</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clear({{ $entry->id }})">Clear</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clearName('{{ $entry->name }}')" title="Every language and audience">Clear all {{ $entry->name }}</x-lynx-admin::ui.button>
                            </td>
                        </tr>
                        @if ($viewing === $entry->id)
                            <tr wire:key="fragment-{{ $entry->id }}-contents">
                                <td colspan="6" class="grid gap-3 pb-4 md:table-cell">
                                    @if ($viewedHtml === null)
                                        <p class="text-muted-foreground">Gone from the cache store; it renders again on the next visit.</p>
                                    @else
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <div class="grid content-start gap-1">
                                                <span class="text-muted-foreground text-xs">Source</span>
                                                <pre class="bg-muted h-72 overflow-auto rounded-lg p-3 font-mono text-xs whitespace-pre-wrap">{{ $viewedHtml }}</pre>
                                            </div>
                                            <div class="grid content-start gap-1">
                                                <span class="text-muted-foreground text-xs">Preview, without the site's styles or scripts</span>
                                                <iframe sandbox="" srcdoc="{{ $viewedHtml }}" title="Preview of {{ $entry->name }}" class="bg-background h-72 w-full rounded-lg border"></iframe>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $entries->links() }}
    @endif
</section>
