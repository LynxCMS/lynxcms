{{--
    The list of keyboard shortcuts (shortcuts.js), opened with "?" or from the user menu: the
    general ones, "g" then a letter for each screen in the sidebar the user may open, and the
    page editor's.
--}}
@php
    $screens = array_values(array_filter(app(\LynxCms\Admin\Navigation::class)->items(), fn (array $item): bool => $item['shortcut'] !== null));
    $groups = [
        __('lynx-admin::shortcuts.general') => [
            [[['mod', 'K'], ['/']], __('lynx-admin::shortcuts.search')],
            [[['?']], __('lynx-admin::shortcuts.this_list')],
        ],
        __('lynx-admin::shortcuts.go_to') => array_map(fn (array $item): array => [[['g', $item['shortcut']]], $item['label']], $screens),
        __('lynx-admin::shortcuts.editor') => [
            [[['mod', 'S']], __('lynx-admin::shortcuts.save')],
            [[['[']], __('lynx-admin::shortcuts.previous_tab')],
            [[[']']], __('lynx-admin::shortcuts.next_tab')],
        ],
    ];
@endphp
<x-lynx-admin::ui.dialog id="lynx-shortcuts">
    <x-lynx-admin::ui.dialog-content class="max-h-[90vh] overflow-y-auto sm:max-w-lg" data-shortcuts>
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>{{ __('lynx-admin::shortcuts.title') }}</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>{{ __('lynx-admin::shortcuts.description') }}</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>
        <div class="grid gap-5" x-data="{ isMac: /Mac|iPhone|iPad/.test(navigator.platform) }">
            @foreach ($groups as $heading => $shortcuts)
                @continue($shortcuts === [])
                <section class="grid gap-2">
                    <h3 class="text-muted-foreground text-xs font-medium uppercase">{{ $heading }}</h3>
                    <dl class="grid gap-y-1.5 text-sm">
                        @foreach ($shortcuts as [$combos, $label])
                            <div class="flex items-center justify-between gap-4 border-b pb-1.5 last:border-0">
                                <dt>{{ $label }}</dt>
                                <dd class="text-muted-foreground flex shrink-0 items-center gap-1 text-xs">
                                    @foreach ($combos as $keys)
                                        @if (! $loop->first) <span class="px-0.5">{{ __('lynx-admin::shortcuts.or') }}</span> @endif
                                        @foreach ($keys as $key)
                                            @if ($key === 'mod')
                                                <x-lynx-admin::ui.kbd><span x-text="isMac ? '⌘' : @js(__('lynx-admin::shortcuts.ctrl'))">{{ __('lynx-admin::shortcuts.ctrl') }}</span></x-lynx-admin::ui.kbd>
                                            @else
                                                <x-lynx-admin::ui.kbd>{{ $key }}</x-lynx-admin::ui.kbd>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
