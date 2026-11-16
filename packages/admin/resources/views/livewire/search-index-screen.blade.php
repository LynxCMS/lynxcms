{{-- The Search index screen: rebuild, languages, templates and their indexed fields, and a page's indexed words. --}}
<div class="grid gap-6">
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-4 p-5" aria-labelledby="search-rebuild">
        <h2 id="search-rebuild" class="font-semibold">{{ __('lynx-admin::search_index.rebuild.title') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.rebuild.hint') }}</p>
        <form wire:submit="rebuild" class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <label for="search-template" class="text-sm font-medium">{{ __('lynx-admin::search_index.rebuild.template') }}</label>
                <select id="search-template" class="blat-select max-w-sm" wire:model="template">
                    <option value="">{{ __('lynx-admin::search_index.rebuild.every_page') }}</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template['name'] }}">{{ $template['label'] }} ({{ $template['name'] }})</option>
                    @endforeach
                </select>
            </div>
            <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled" wire:target="rebuild">{{ __('lynx-admin::search_index.rebuild.button') }}</x-lynx-admin::ui.button>
        </form>
        @if ($status)
            <p class="text-sm" role="status">{{ $status }}</p>
        @endif
    </section>

    <section aria-labelledby="search-languages" class="grid gap-3">
        <h2 id="search-languages" class="font-semibold">{{ __('lynx-admin::search_index.languages.title') }}</h2>
        <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.languages.language') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.languages.indexed') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.languages.own') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.languages.plurals') }}</x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @foreach ($languages as $language)
                    <x-lynx-admin::ui.table-row wire:key="search-language-{{ $language['code'] }}" data-language="{{ $language['code'] }}">
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $language['name'] }} <span class="text-muted-foreground font-mono text-sm">{{ $language['code'] }}</span></x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            {{ __('lynx-admin::search_index.languages.count', ['rows' => $language['rows'], 'pages' => $language['pages']]) }}
                            @if ($language['rows'] < $language['pages'])
                                <x-lynx-admin::ui.badge variant="outline" class="ms-1">{{ __('lynx-admin::search_index.languages.incomplete') }}</x-lynx-admin::ui.badge>
                            @endif
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2" data-own="{{ $language['code'] }}">{{ $language['own'] ?? __('lynx-admin::search_index.languages.own_shared') }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ __($language['plurals'] ? 'lynx-admin::search_index.languages.plurals_on' : 'lynx-admin::search_index.languages.plurals_off') }}</x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @endforeach
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.languages.fallback_hint') }}</p>
    </section>

    <section aria-labelledby="search-templates" class="grid gap-3">
        <h2 id="search-templates" class="font-semibold">{{ __('lynx-admin::search_index.templates.title') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.templates.hint') }}</p>
        <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.template') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.pages') }}</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.fields') }}</x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @foreach ($templates as $template)
                    <x-lynx-admin::ui.table-row wire:key="search-template-{{ $template['name'] }}" data-template="{{ $template['name'] }}">
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            {{ $template['label'] }} <span class="text-muted-foreground font-mono text-sm">{{ $template['name'] }}</span>
                            @unless ($template['has_url'])
                                <x-lynx-admin::ui.badge variant="outline" class="ms-1">{{ __('lynx-admin::search_index.templates.no_url') }}</x-lynx-admin::ui.badge>
                            @endunless
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $template['pages'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-mono text-sm">{{ implode(', ', $template['fields']) }}</x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @endforeach
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
    </section>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-4 p-5" aria-labelledby="search-lookup">
        <h2 id="search-lookup" class="font-semibold">{{ __('lynx-admin::search_index.lookup.title') }}</h2>
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
</div>
