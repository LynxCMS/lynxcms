{{-- Settings > Page passwords: templates on or off, and the protected pages. --}}
<div class="grid gap-6">
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm">
        <header class="border-b px-5 py-4">
            <h2 class="font-semibold">Templates</h2>
            <p class="text-muted-foreground text-sm">Pages of these templates have a Password field in the page editor's sidebar. Turning a template off keeps the passwords, but its pages become public.</p>
        </header>
        <ul class="divide-y divide-border">
            @foreach ($templates as $template)
                <li class="flex items-center gap-3 px-5 py-3" wire:key="template-{{ $template['name'] }}">
                    <span class="font-medium">{{ $template['label'] }}</span>
                    <span class="text-muted-foreground font-mono text-xs">{{ $template['name'] }}</span>
                    <span class="ms-auto">
                        @if ($template['isOn'])
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="disable(@js($template['name']))">Turn off</x-lynx-admin::ui.button>
                        @else
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="enable(@js($template['name']))">Turn on</x-lynx-admin::ui.button>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm">
        <header class="border-b px-5 py-4">
            <h2 class="font-semibold">Protected pages</h2>
        </header>
        @if ($pages === [])
            <p class="text-muted-foreground px-5 py-4 text-sm">No page has a password yet.</p>
        @else
            <ul class="divide-y divide-border">
                @foreach ($pages as $page)
                    <li class="flex items-center gap-3 px-5 py-3" wire:key="page-{{ $page['id'] }}">
                        <x-lynx-admin::ui.icon name="key-round" class="text-muted-foreground size-4" aria-hidden="true" />
                        <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="font-medium underline-offset-4 hover:underline">{{ $page['title'] }}</a>
                        <span class="text-muted-foreground text-sm">{{ $page['url'] }}</span>
                        <span class="text-muted-foreground ms-auto text-sm">{{ $page['below'] === 0 ? 'No pages below' : $page['below'].' '.($page['below'] === 1 ? 'page' : 'pages').' below' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
