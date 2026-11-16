{{-- Search index overview: each language's rows, and the fields the site's and the system templates index. --}}
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
    <div data-site-templates>
        @include('lynx-admin::livewire.partials.search-index-templates', ['rows' => $siteTemplates, 'withOwner' => false])
    </div>
    @if ($systemTemplates !== [])
        <h3 class="mt-3 text-sm font-semibold">{{ __('lynx-admin::search_index.templates.system') }}</h3>
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::search_index.templates.system_hint') }}</p>
        <div data-system-templates>
            @include('lynx-admin::livewire.partials.search-index-templates', ['rows' => $systemTemplates, 'withOwner' => true])
        </div>
    @endif
</section>
