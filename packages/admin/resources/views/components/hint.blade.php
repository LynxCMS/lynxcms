{{--
    A help icon beside a heading or a label that explains it in a tooltip, so the screen stays
    uncluttered. It opens on hover, on keyboard focus and on a tap (touch screens have no hover);
    Escape closes it. The icon is a button named by $label, and the text describes it, so screen
    readers read both. The accent colour at 80%, lifted in dark mode as the home screen's icons are.

    <x-lynx-admin::hint :label="__('reports.why_label')">{{ __('reports.why') }}</x-lynx-admin::hint>

    label  the button's name, said by screen readers: what the hint answers ("How names fill again")
    side   where the tooltip opens: top, right, bottom (default) or left
--}}
@props(['label', 'side' => 'bottom'])
<x-lynx-admin::ui.tooltip {{ $attributes->class('flex') }}>
    <x-lynx-admin::ui.tooltip-trigger class="flex">
        <button type="button" class="text-primary dark:text-[color-mix(in_oklab,var(--primary),var(--foreground)_30%)] focus-visible:ring-ring/50 flex rounded-full opacity-80 outline-none hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-[3px]" x-on:click="open = ! open" aria-label="{{ $label }}">
            <x-lynx-admin::ui.icon name="circle-help" class="size-4" aria-hidden="true" />
        </button>
    </x-lynx-admin::ui.tooltip-trigger>
    <x-lynx-admin::ui.tooltip-content :side="$side">{{ $slot }}</x-lynx-admin::ui.tooltip-content>
</x-lynx-admin::ui.tooltip>
