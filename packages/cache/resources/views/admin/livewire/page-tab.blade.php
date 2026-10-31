@php($code = fn (string $text): string => '<code class="font-mono">'.e($text).'</code>')
<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="page-cache-status" class="bg-card grid gap-3 rounded-xl border p-5">
        <h2 id="page-cache-status" class="font-medium">{{ __('lynx-cache::admin.tabview.heading') }}</h2>

        @if ($reason === 'off')
            <p role="note" class="text-sm">{{ __('lynx-cache::admin.tabview.off') }}@if ($canManage) {!! __('lynx-cache::admin.tabview.off_fix', ['env' => $code('LYNX_PAGE_CACHE=true'), 'file' => $code('.env')]) !!}@endif</p>
        @elseif ($reason === 'template')
            <p role="note" class="text-sm">{!! __('lynx-cache::admin.tabview.template_off', ['template' => '<span class="font-mono">'.e($page->template()->name).'</span>']) !!}@if ($canManage) {{ __('lynx-cache::admin.tabview.template_fix') }}@endif</p>
        @elseif ($reason === 'unpublished')
            <p role="note" class="text-sm">{{ __('lynx-cache::admin.tabview.unpublished') }}</p>
        @elseif ($entries->isEmpty())
            <p class="text-sm"><span class="font-medium">{{ __('lynx-cache::admin.tabview.not_stored') }}</span> {{ __('lynx-cache::admin.tabview.not_stored_help') }}</p>
        @else
            <p class="text-sm"><span class="font-medium">{{ __('lynx-cache::admin.tabview.yes') }}</span> {{ __($entries->count() === 1 ? 'lynx-cache::admin.tabview.yes_url' : 'lynx-cache::admin.tabview.yes_urls') }}</p>
        @endif

        @if ($entries->isNotEmpty())
            <table class="w-full text-left text-sm">
                <thead class="text-muted-foreground">
                    <tr><th class="py-1 font-normal">{{ __('lynx-cache::admin.tabview.url') }}</th>@if (count($languages) > 1)<th class="py-1 font-normal">{{ __('lynx-cache::admin.tabview.language') }}</th>@endif<th class="py-1 font-normal">{{ __('lynx-cache::admin.tabview.stored') }}</th><th class="py-1 font-normal">{{ __('lynx-cache::admin.tabview.expires') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" class="border-t">
                            <td class="py-1 font-mono text-xs">{{ \LynxCms\Core\Pages\PageUrl::of('', $entry->path) }}{{ $entry->query === '' ? '' : '?'.$entry->query }}</td>
                            @if (count($languages) > 1)<td class="py-1">{{ $languages[$entry->language_id] ?? '' }}</td>@endif
                            <td class="py-1">{{ $entry->created_at->diffForHumans() }}</td>
                            <td class="py-1">{{ $entry->expires_at?->diffForHumans() ?? __('lynx-cache::admin.tabview.when_changes') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($canManage && $rule !== null && $reason === null)
            <ul class="text-muted-foreground grid list-disc gap-1 pl-5 text-sm">
                <li>{!! __('lynx-cache::admin.tabview.hosts', ['hosts' => '<span class="font-mono">'.e(implode(', ', $hosts)).'</span>']) !!}</li>
                <li>{{ $rule->lifetime === 0 ? __('lynx-cache::admin.tabview.kept_until') : __('lynx-cache::admin.tabview.kept_for', ['seconds' => $rule->lifetime]) }}</li>
                <li>{{ $rule->parameters === [] ? __('lynx-cache::admin.tabview.no_query') : __('lynx-cache::admin.tabview.query', ['parameters' => implode(', ', $rule->parameters)]) }}</li>
                <li>{{ $rule->clearsEverything() ? __('lynx-cache::admin.tabview.clears_all') : __('lynx-cache::admin.tabview.clears', ['scopes' => implode(', ', $rule->clear)]) }}</li>
                <li>{{ __($rule->refreshesToken ? 'lynx-cache::admin.tabview.token' : 'lynx-cache::admin.tabview.no_token') }}</li>
            </ul>
        @endif

        @if ($canManage)
        <div class="flex flex-wrap items-center gap-2">
            @if ($entries->isNotEmpty())
                <x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="clear">{{ __('lynx-cache::admin.tabview.clear') }}</x-lynx-admin::ui.button>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="sm" :href="route('lynx.admin.page-cache', ['page' => $page->id])">{{ __('lynx-cache::admin.tabview.settings') }}</x-lynx-admin::ui.button>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
        @endif
    </section>

    {{-- How to use the cache: for the people who manage it. --}}
    @if ($canManage)
    <section aria-labelledby="page-cache-help" class="bg-card grid gap-3 rounded-xl border p-5 text-sm">
        <h2 id="page-cache-help" class="font-medium">{{ __('lynx-cache::admin.tabview.help') }}</h2>
        <ul class="grid list-disc gap-2 pl-5">
            <li><span class="font-medium">{{ __('lynx-cache::admin.tabview.guests_lead') }}</span> {{ __('lynx-cache::admin.tabview.guests') }}</li>
            <li><span class="font-medium">{{ __('lynx-cache::admin.tabview.saving_lead') }}</span> {{ __('lynx-cache::admin.tabview.saving') }}</li>
            <li><span class="font-medium">{{ __('lynx-cache::admin.tabview.deploy_lead') }}</span> {{ __('lynx-cache::admin.tabview.deploy') }}</li>
            <li><span class="font-medium">{{ __('lynx-cache::admin.tabview.personal_lead') }}</span> {!! __('lynx-cache::admin.tabview.personal', ['no_store' => $code('no-store'), 'island' => $code('<'.'x-lynx-island>')]) !!}</li>
            <li><span class="font-medium">{{ __('lynx-cache::admin.tabview.check_lead') }}</span> {!! __('lynx-cache::admin.tabview.check', ['env' => $code('LYNX_PAGE_CACHE_DEBUG=true'), 'header' => $code('X-Lynx-Cache')]) !!}</li>
        </ul>
    </section>
    @endif
</div>
