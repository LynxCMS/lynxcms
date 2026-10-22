@props(['inset' => false])

<x-lynx-admin::ui.menu-label
    :data-slot="'dropdown-menu-label'"
    :inset="$inset"
    {{ $attributes }}
>{{ $slot }}</x-lynx-admin::ui.menu-label>
