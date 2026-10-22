{{--
    Vertical menu whose branches open on click, built on <details>: no JavaScript, keyboard
    accessible, and the branch holding the current page starts open. Restyle with --lynx-menu-*.
--}}
@once
    <style>
        .lynx-menu-accordion { --lynx-menu-fg: inherit; --lynx-menu-muted: #6b7280; --lynx-menu-border: #e5e7eb; --lynx-menu-indent: 1rem; --lynx-menu-active: currentColor; }
        .lynx-menu-accordion ul { list-style: none; margin: 0; padding: 0; }
        .lynx-menu-accordion ul ul { padding-inline-start: var(--lynx-menu-indent); }
        .lynx-menu-accordion .lynx-menu-level-0 > .lynx-menu-item { border-bottom: 1px solid var(--lynx-menu-border); }
        .lynx-menu-accordion summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .5rem 0; cursor: pointer; list-style: none; }
        .lynx-menu-accordion summary::-webkit-details-marker { display: none; }
        .lynx-menu-accordion summary::after { content: "+"; color: var(--lynx-menu-muted); }
        .lynx-menu-accordion details[open] > summary::after { content: "−"; }
        .lynx-menu-accordion .lynx-menu-link, .lynx-menu-accordion .lynx-menu-section { display: block; padding: .5rem 0; color: var(--lynx-menu-fg); text-decoration: none; }
        .lynx-menu-accordion summary .lynx-menu-link, .lynx-menu-accordion summary .lynx-menu-section { padding: 0; }
        .lynx-menu-accordion .lynx-menu-section { font-weight: 600; }
        .lynx-menu-accordion [aria-current="page"] { color: var(--lynx-menu-active); font-weight: 600; }
    </style>
@endonce
<nav {{ $attributes->merge(['class' => trim('lynx-menu lynx-menu-accordion '.$menu->class)]) }} aria-label="{{ $menu->title }}">
    @include('lynx-menus::menus.partials.accordion-items', ['items' => $menu->items, 'level' => 0])
</nav>
