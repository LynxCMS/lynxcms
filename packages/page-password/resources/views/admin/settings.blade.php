{{-- Settings > Page passwords: templates on or off, and the protected pages. --}}
<div class="grid gap-6">
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm">
        <header class="border-b px-5 py-4">
            <h2 class="font-semibold">{{ __('lynx-page-password::admin.templates') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-page-password::admin.templates_help') }}</p>
        </header>
        <ul class="divide-y divide-border">
            @foreach ($templates as $template)
                <li class="flex items-center gap-3 px-5 py-3" wire:key="template-{{ $template['name'] }}">
                    <span class="font-medium">{{ $template['label'] }}</span>
                    <span class="text-muted-foreground font-mono text-xs">{{ $template['name'] }}</span>
                    <span class="ms-auto">
                        @if ($template['isOn'])
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="disable(@js($template['name']))">{{ __('lynx-page-password::admin.turn_off') }}</x-lynx-admin::ui.button>
                        @else
                            <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="enable(@js($template['name']))">{{ __('lynx-page-password::admin.turn_on') }}</x-lynx-admin::ui.button>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm">
        <header class="border-b px-5 py-4">
            <h2 class="font-semibold">{{ __('lynx-page-password::admin.pages') }}</h2>
        </header>
        @if ($pages === [])
            <p class="text-muted-foreground px-5 py-4 text-sm">{{ __('lynx-page-password::admin.empty') }}</p>
        @else
            <ul class="divide-y divide-border">
                @foreach ($pages as $page)
                    <li class="flex items-center gap-3 px-5 py-3" wire:key="page-{{ $page['id'] }}">
                        <x-lynx-admin::ui.icon name="key-round" class="text-muted-foreground size-4" aria-hidden="true" />
                        <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="font-medium underline-offset-4 hover:underline">{{ $page['title'] }}</a>
                        <span class="text-muted-foreground text-sm">{{ $page['url'] }}</span>
                        <span class="text-muted-foreground ms-auto text-sm">{{ trans_choice('lynx-page-password::admin.below', $page['below']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
