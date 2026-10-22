@props([
    'value' => '',
    'closeOnSelect' => false,
])

<x-lynx-admin::ui.menu-radio-item
    :data-slot="'dropdown-menu-radio-item'"
    :value="$value"
    :close-on-select="$closeOnSelect"
    {{ $attributes }}
>{{ $slot }}</x-lynx-admin::ui.menu-radio-item>
