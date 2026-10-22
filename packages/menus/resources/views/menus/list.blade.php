{{-- Plain nested list without styles or JavaScript: the starting point for custom menus. --}}
<nav {{ $attributes->merge(['class' => trim('lynx-menu lynx-menu-list '.$menu->class)]) }} aria-label="{{ $menu->title }}">
    @include('lynx-menus::menus.partials.list-items', ['items' => $menu->items, 'level' => 0])
</nav>
