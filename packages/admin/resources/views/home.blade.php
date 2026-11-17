{{--
    The admin's home screen (HomeController): a welcome with today's date and common actions, the
    row of stats, then two columns of panels (HomeScreen). The main one holds what waits for
    someone: pages with a draft, the next scheduled changes, then what modules add. The side one
    holds the record of what changed last. On small screens the main column comes first; with
    nothing in the side column, the main one takes the whole width.
--}}
<x-lynx-admin::layout :title="__('lynx-admin::home.title')">
    <x-lynx-admin::ui.page-header :title="$name === null ? __('lynx-admin::home.welcome_plain') : __('lynx-admin::home.welcome', ['name' => $name])"
        :description="__('lynx-admin::home.today', ['date' => $today])" class="mb-6 [&_h1]:text-primary dark:[&_h1]:text-[color-mix(in_oklab,var(--primary),var(--foreground)_30%)] [&_h1]:text-2xl [&_h1]:font-semibold" data-home-welcome>
        @if ($actions !== [])
            <x-slot:actions>
                <nav class="flex flex-wrap gap-2" aria-label="{{ __('lynx-admin::home.actions.label') }}">
                    @foreach ($actions as $action)
                        <x-lynx-admin::ui.button :href="$action['url']" :variant="$loop->first ? 'default' : 'outline'" data-home-action="{{ $action['key'] }}">
                            @if ($action['icon'] !== null)
                                <x-lynx-admin::ui.icon :name="$action['icon']" aria-hidden="true" />
                            @endif
                            {{ $action['label'] }}
                        </x-lynx-admin::ui.button>
                    @endforeach
                </nav>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>

    <x-lynx-admin::home.stats :stats="$stats" class="mb-10" />

    <div @class(['grid gap-10', 'lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-12' => $sidePanels !== []])>
        <div class="grid min-w-0 content-start gap-10" data-home-main>
            @foreach ($panels as $panel)
                @include($panel)
            @endforeach
        </div>

        @if ($sidePanels !== [])
            <div class="grid min-w-0 content-start gap-10 lg:border-s lg:ps-12" data-home-side>
                @foreach ($sidePanels as $panel)
                    @include($panel)
                @endforeach
            </div>
        @endif
    </div>
</x-lynx-admin::layout>
