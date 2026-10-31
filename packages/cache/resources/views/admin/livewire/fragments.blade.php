{{-- The Page parts tab: parts of pages templates keep with @@lynxCache or lynx()->cache() (fragments in code), to look at and clear. --}}
<section aria-label="{{ __('lynx-cache::admin.tabs.fragments') }}" class="grid max-w-4xl gap-5">
    <div class="grid max-w-2xl gap-2 text-sm">
        <p>{{ __('lynx-cache::admin.fragments.intro') }}</p>
        <p class="text-muted-foreground">{!! __('lynx-cache::admin.fragments.detail', ['code' => '<code class="font-mono">@@lynxCache</code>']) !!}</p>
        <p class="text-muted-foreground" data-fragments-total="{{ $total }}">{{ trans_choice('lynx-cache::admin.fragments.total', $total) }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <input type="search" class="blat-input w-64" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-cache::admin.fragments.filter') }}" aria-label="{{ __('lynx-cache::admin.fragments.filter_label') }}">
        <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearAll">{{ __('lynx-cache::admin.fragments.clear_all') }}</x-lynx-admin::ui.button>
        @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
    </div>

    @if ($entries->isNotEmpty())
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="text-muted-foreground bg-muted/40">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('lynx-cache::admin.fragments.name') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('lynx-cache::admin.fragments.language') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('lynx-cache::admin.fragments.cleared_by') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('lynx-cache::admin.fragments.size') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('lynx-cache::admin.fragments.stored') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">{{ __('lynx-cache::admin.fragments.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr class="border-t" wire:key="fragment-{{ $entry->id }}" data-fragment="{{ $entry->name }}">
                            <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $entry->name }}@if ($entry->audience !== 'guest') <span class="text-muted-foreground" title="{{ __('lynx-cache::admin.fragments.unlocked') }}">*</span>@endif</td>
                            <td class="py-2 pr-3">{{ $entry->language }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ implode(', ', array_map(fn ($dependency) => $dependency === 'any' ? __('lynx-cache::admin.fragments.any_change') : $dependency, $entry->dependencies())) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ number_format($entry->size / 1024, 1) }} KB</td>
                            <td class="py-2 pr-3" title="{{ $entry->created_at }}">{{ $entry->created_at->diffForHumans() }}@if ($entry->expires_at){{ __('lynx-cache::admin.fragments.until', ['when' => $entry->expires_at->diffForHumans()]) }}@endif</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="view({{ $entry->id }})" :aria-expanded="$viewing === $entry->id ? 'true' : 'false'">{{ __($viewing === $entry->id ? 'lynx-cache::admin.fragments.hide' : 'lynx-cache::admin.fragments.view') }}</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clear({{ $entry->id }})">{{ __('lynx-cache::admin.fragments.clear') }}</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clearName('{{ $entry->name }}')" title="{{ __('lynx-cache::admin.fragments.every_language') }}">{{ __('lynx-cache::admin.fragments.clear_name', ['name' => $entry->name]) }}</x-lynx-admin::ui.button>
                            </td>
                        </tr>
                        @if ($viewing === $entry->id)
                            <tr wire:key="fragment-{{ $entry->id }}-contents">
                                <td colspan="6" class="grid gap-3 px-4 pb-5 md:table-cell">
                                    @if ($viewedHtml === null)
                                        <p class="text-muted-foreground">{{ __('lynx-cache::admin.fragments.gone') }}</p>
                                    @else
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <div class="grid content-start gap-1">
                                                <span class="text-muted-foreground text-xs">{{ __('lynx-cache::admin.fragments.source') }}</span>
                                                <pre class="bg-muted h-72 overflow-auto rounded-lg p-3 font-mono text-xs whitespace-pre-wrap">{{ $viewedHtml }}</pre>
                                            </div>
                                            <div class="grid content-start gap-1">
                                                <span class="text-muted-foreground text-xs">{{ __('lynx-cache::admin.fragments.preview') }}</span>
                                                <iframe sandbox="" srcdoc="{{ $viewedHtml }}" title="{{ __('lynx-cache::admin.fragments.preview_of', ['name' => $entry->name]) }}" class="bg-background h-72 w-full rounded-lg border"></iframe>
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
