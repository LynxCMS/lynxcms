{{-- What is stored now: in all, for one page the editor linked to, and per template; and clearing it. --}}
<div class="grid gap-8">
    @unless ($isEnabled)
        <x-lynx-admin::ui.alert tone="warning" role="note">
            <x-lucide-circle-pause aria-hidden="true" />
            <x-lynx-admin::ui.alert-description><p>The page cache is off. Set <code class="font-mono">LYNX_PAGE_CACHE=true</code> to store pages; the rules saved here apply then.</p></x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endunless

    <dl class="grid gap-6 sm:grid-cols-3">
        <div class="grid gap-1"><dt class="text-muted-foreground text-sm">Stored pages</dt><dd class="text-2xl font-semibold tabular-nums" data-stored-total="{{ $total }}">{{ number_format($total) }}</dd></div>
        <div class="grid gap-1"><dt class="text-muted-foreground text-sm">Kept by</dt><dd class="font-mono text-sm">{{ $driver }}</dd></div>
        <div class="grid gap-1"><dt class="text-muted-foreground text-sm">For requests to</dt><dd class="font-mono text-sm break-all">{{ implode(', ', $hosts) }}</dd></div>
    </dl>

    @if ($page !== null)
        <section class="grid gap-3 border-t pt-6" aria-labelledby="cache-this-page">
            <h2 id="cache-this-page" class="text-base font-semibold">This page</h2>
            @if ($pageEntries->isEmpty())
                <p class="text-muted-foreground text-sm">Nothing stored for it.</p>
            @else
                <ul class="grid gap-1 font-mono text-sm">
                    @foreach ($pageEntries as $entry)
                        <li>/{{ $entry->path }}{{ $entry->query === '' ? '' : '?'.$entry->query }}</li>
                    @endforeach
                </ul>
                <x-lynx-admin::ui.button type="button" variant="outline" size="sm" class="justify-self-start" wire:click="clearPage">Clear this page</x-lynx-admin::ui.button>
            @endif
        </section>
    @endif

    <section class="grid gap-4 border-t pt-6" aria-labelledby="cache-by-template">
        <h2 id="cache-by-template" class="text-base font-semibold">By template</h2>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="text-muted-foreground bg-muted/40">
                    <tr><th scope="col" class="px-4 py-2.5 font-medium">Template</th><th scope="col" class="px-4 py-2.5 font-medium">Rule</th><th scope="col" class="px-4 py-2.5 text-right font-medium">Stored</th><th scope="col" class="px-4 py-2.5"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($templates as $name => $label)
                        @php($count = $counts[$name] ?? 0)
                        <tr wire:key="stored-{{ $name }}">
                            <td class="px-4 py-3"><span class="font-medium">{{ $label }}</span> <span class="text-muted-foreground font-mono text-xs">{{ $name }}</span></td>
                            <td class="text-muted-foreground px-4 py-3">{{ $rules[$name]['cache'] ? 'Cached' : 'Not cached' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums" data-template-stored="{{ $name }}" data-count="{{ $count }}">{{ number_format($count) }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($count > 0)<x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clearTemplate('{{ $name }}')">Clear</x-lynx-admin::ui.button>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearAll">Clear every page</x-lynx-admin::ui.button>
            @if ($statusFor === 'stored') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
    </section>
</div>
