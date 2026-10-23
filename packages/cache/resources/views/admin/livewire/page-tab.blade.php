<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="page-cache-status" class="bg-card grid gap-3 rounded-xl border p-5">
        <h2 id="page-cache-status" class="font-medium">Is this page cached?</h2>

        @if ($reason === 'off')
            <p role="note" class="text-sm">The page cache is off for the whole site, so every visit renders the page.@if ($canManage) Set <code class="font-mono">LYNX_PAGE_CACHE=true</code> in <code class="font-mono">.env</code> to turn it on.@endif</p>
        @elseif ($reason === 'template')
            <p role="note" class="text-sm">Pages of the <span class="font-mono">{{ $page->template()->name }}</span> template are not stored, so every visit renders this page.@if ($canManage) Turn on "Store pages of this template" in the page cache settings to store them.@endif</p>
        @elseif ($reason === 'unpublished')
            <p role="note" class="text-sm">This page is unpublished. Guests can't see it, so it is never stored.</p>
        @elseif ($entries->isEmpty())
            <p class="text-sm"><span class="font-medium">Not stored yet.</span> The next guest visit stores it, and later guests get the stored copy.</p>
        @else
            <p class="text-sm"><span class="font-medium">Yes, this page is in the cache.</span> Guests get the cached version of this page when they visit {{ $entries->count() === 1 ? 'this URL' : 'these URLs' }}:</p>
        @endif

        @if ($entries->isNotEmpty())
            <table class="w-full text-left text-sm">
                <thead class="text-muted-foreground">
                    <tr><th class="py-1 font-normal">URL</th><th class="py-1 font-normal">Language</th><th class="py-1 font-normal">Stored</th><th class="py-1 font-normal">Expires</th></tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" class="border-t">
                            <td class="py-1 font-mono text-xs">{{ \LynxCms\Core\Pages\PageUrl::of('', $entry->path) }}{{ $entry->query === '' ? '' : '?'.$entry->query }}</td>
                            <td class="py-1">{{ $languages[$entry->language_id] ?? '' }}</td>
                            <td class="py-1">{{ $entry->created_at->diffForHumans() }}</td>
                            <td class="py-1">{{ $entry->expires_at?->diffForHumans() ?? 'When content changes' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($canManage && $rule !== null && $reason === null)
            <ul class="text-muted-foreground grid list-disc gap-1 pl-5 text-sm">
                <li>Pages are stored for requests to <span class="font-mono">{{ implode(', ', $hosts) }}</span>; other host names always render the page.</li>
                <li>{{ $rule->lifetime === 0 ? 'A stored copy is kept until its content changes.' : 'A stored copy is kept '.$rule->lifetime.' seconds, or until its content changes.' }}</li>
                <li>{{ $rule->parameters === [] ? 'Addresses with a query string are not stored.' : 'Query parameters stored: '.implode(', ', $rule->parameters).'.' }}</li>
                <li>{{ $rule->clearsEverything() ? 'Saving any page clears every stored page.' : 'Saving a page of this template clears: '.implode(', ', $rule->clear).'.' }}</li>
                <li>{{ $rule->refreshesToken ? 'With a form or Livewire, the page is still stored, and a script gives each visitor a fresh security token.' : 'With a form or Livewire, the page is not stored.' }}</li>
            </ul>
        @endif

        @if ($canManage)
        <div class="flex flex-wrap items-center gap-2">
            @if ($entries->isNotEmpty())
                <x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="clear">Clear this page</x-lynx-admin::ui.button>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="sm" :href="route('lynx.admin.page-cache', ['page' => $page->id])">Page cache settings</x-lynx-admin::ui.button>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
        @endif
    </section>

    {{-- How to use the cache: for the people who manage it. --}}
    @if ($canManage)
    <section aria-labelledby="page-cache-help" class="bg-card grid gap-3 rounded-xl border p-5 text-sm">
        <h2 id="page-cache-help" class="font-medium">How the page cache works</h2>
        <ul class="grid list-disc gap-2 pl-5">
            <li><span class="font-medium">Only guests get stored copies.</span> Signed-in people always see the live page, so to see what guests get, open the page in a private window.</li>
            <li><span class="font-medium">Saving clears what changed.</span> When you save, publish or move a page, the stored copies it affects are dropped, and the next guest visit stores them again. You don't need to clear pages by hand after editing.</li>
            <li><span class="font-medium">Clear by hand after a deploy</span> or when something outside the content changed, such as a template file or a menu built in code.</li>
            <li><span class="font-medium">Pages with personal content are never stored:</span> responses that set cookies of their own or are marked <code class="font-mono">no-store</code>, and pages visitors see after unlocking a page password. Parts that change per visitor belong in an island (<code class="font-mono">&lt;x-lynx-island&gt;</code>).</li>
            <li><span class="font-medium">To check a page,</span> set <code class="font-mono">LYNX_PAGE_CACHE_DEBUG=true</code>: responses then carry an <code class="font-mono">X-Lynx-Cache</code> header saying whether the page was stored, and why not.</li>
        </ul>
    </section>
    @endif
</div>
