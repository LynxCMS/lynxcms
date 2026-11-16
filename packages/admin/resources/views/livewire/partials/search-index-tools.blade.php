{{-- Search index tools: rebuild every page, a site template or a system template, and test a page's indexed words. --}}
<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-4 p-5" aria-labelledby="search-rebuild">
    <h2 id="search-rebuild" class="font-semibold">{{ __('lynx-admin::search_index.rebuild.title') }}</h2>
    <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.rebuild.hint') }}</p>
    <form wire:submit="rebuild" class="flex flex-wrap items-end gap-3">
        <div class="grid gap-1.5">
            <label for="search-template" class="text-sm font-medium">{{ __('lynx-admin::search_index.rebuild.template') }}</label>
            <select id="search-template" class="blat-select max-w-sm" wire:model.live="template">
                <option value="">{{ __('lynx-admin::search_index.rebuild.every_page') }}</option>
                @foreach ($siteTemplates as $template)
                    <option value="{{ $template['name'] }}">{{ $template['label'] }} ({{ $template['name'] }})</option>
                @endforeach
            </select>
        </div>
        @if ($systemTemplates !== [])
            <div class="grid gap-1.5">
                <label for="search-system-template" class="text-sm font-medium">{{ __('lynx-admin::search_index.rebuild.system_template') }}</label>
                <select id="search-system-template" class="blat-select max-w-sm" wire:model.live="systemTemplate">
                    <option value="">{{ __('lynx-admin::search_index.rebuild.no_system_template') }}</option>
                    @foreach ($systemTemplates as $template)
                        <option value="{{ $template['name'] }}">{{ $template['label'] }} ({{ $template['owner'] }})</option>
                    @endforeach
                </select>
            </div>
        @endif
        <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled" wire:target="rebuild">{{ __('lynx-admin::search_index.rebuild.button') }}</x-lynx-admin::ui.button>
    </form>
    @if ($status)
        <p class="text-sm" role="status">{{ $status }}</p>
    @endif
</section>

<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-4 p-5" aria-labelledby="search-lookup">
    <h2 id="search-lookup" class="font-semibold">{{ __('lynx-admin::search_index.lookup.title') }}</h2>
    <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.lookup.hint') }}</p>
    <div class="grid gap-1.5">
        <label for="search-page" class="text-sm font-medium">{{ __('lynx-admin::search_index.lookup.page') }}</label>
        <input id="search-page" type="text" class="blat-input max-w-sm font-mono" wire:model.live.debounce.400ms="page" placeholder="{{ __('lynx-admin::search_index.lookup.placeholder') }}" autocomplete="off">
    </div>
    @if (trim($page) !== '' && $found === null)
        <p class="text-muted-foreground text-sm" role="status">{{ __('lynx-admin::search_index.lookup.missing') }}</p>
    @elseif ($found !== null)
        <p class="text-sm"><a href="{{ route('lynx.admin.pages.edit', $found->id) }}" class="font-medium underline">{{ $found->title }}</a></p>
        @if ($rows === [])
            <p class="text-muted-foreground text-sm" role="status">{{ __('lynx-admin::search_index.lookup.not_indexed') }}</p>
        @else
            <dl class="grid gap-3">
                @foreach ($rows as $code => $content)
                    <div data-row="{{ $code }}">
                        <dt class="text-sm font-medium font-mono">{{ $code }}</dt>
                        <dd class="text-muted-foreground font-mono text-sm break-words">{{ $content }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    @endif
</section>
