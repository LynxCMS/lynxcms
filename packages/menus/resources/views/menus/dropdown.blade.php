{{--
    Horizontal bar whose submenus open on hover and on keyboard focus. Below 48rem the list
    becomes a panel opened by a "Menu" button, using the HTML popover attribute: no JavaScript,
    Esc and tapping outside close it. Restyle with the --lynx-menu-* variables or publish the view.
--}}
@php($panelId = 'lynx-menu-'.$menu->name.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)))
@once
    <style>
        .lynx-menu-dropdown { --lynx-menu-gap: 1.25rem; --lynx-menu-bg: #fff; --lynx-menu-fg: inherit; --lynx-menu-muted: #6b7280; --lynx-menu-border: #e5e7eb; --lynx-menu-radius: .5rem; --lynx-menu-shadow: 0 8px 24px rgb(0 0 0 / .08); --lynx-menu-active: currentColor; --lynx-menu-indent: 1rem; }
        .lynx-menu-dropdown ul { list-style: none; margin: 0; padding: 0; }
        .lynx-menu-dropdown .lynx-menu-link, .lynx-menu-dropdown .lynx-menu-section { display: block; padding: .375rem 0; color: var(--lynx-menu-fg); text-decoration: none; font: inherit; background: none; border: 0; }
        .lynx-menu-dropdown .is-active-trail > .lynx-menu-link { color: var(--lynx-menu-active); font-weight: 600; }
        .lynx-menu-dropdown .lynx-menu-toggle, .lynx-menu-dropdown .lynx-menu-close { padding: .375rem .75rem; color: var(--lynx-menu-fg); font: inherit; background: none; border: 1px solid var(--lynx-menu-border); border-radius: var(--lynx-menu-radius); cursor: pointer; }

        /* Wide screens: the panel is always shown as a bar, submenus open on hover and focus. */
        @media (min-width: 48rem) {
            .lynx-menu-dropdown .lynx-menu-toggle, .lynx-menu-dropdown .lynx-menu-close { display: none; }
            .lynx-menu-dropdown .lynx-menu-panel { display: block; position: static; inset: auto; width: auto; height: auto; margin: 0; padding: 0; overflow: visible; color: inherit; background: none; border: 0; }
            .lynx-menu-dropdown .lynx-menu-level-0 { display: flex; flex-wrap: wrap; gap: var(--lynx-menu-gap); align-items: center; }
            .lynx-menu-dropdown .lynx-menu-item { position: relative; }
            .lynx-menu-dropdown .lynx-menu-section { cursor: pointer; }
            .lynx-menu-dropdown .lynx-menu-level-0 > .has-children > :is(a, button, span)::after { content: "▾"; margin-inline-start: .3em; font-size: .75em; color: var(--lynx-menu-muted); }
            .lynx-menu-dropdown .lynx-menu-item > ul { position: absolute; z-index: 50; top: 100%; inset-inline-start: 0; min-width: 12rem; padding: .375rem; background: var(--lynx-menu-bg); border: 1px solid var(--lynx-menu-border); border-radius: var(--lynx-menu-radius); box-shadow: var(--lynx-menu-shadow); visibility: hidden; opacity: 0; transform: translateY(4px); transition: opacity 120ms, transform 120ms, visibility 120ms; }
            .lynx-menu-dropdown .lynx-menu-item > ul ul { top: -.375rem; inset-inline-start: 100%; }
            .lynx-menu-dropdown .lynx-menu-item:hover > ul, .lynx-menu-dropdown .lynx-menu-item:focus-within > ul { visibility: visible; opacity: 1; transform: none; }
            .lynx-menu-dropdown .lynx-menu-item ul .lynx-menu-link, .lynx-menu-dropdown .lynx-menu-item ul .lynx-menu-section { padding: .375rem .625rem; border-radius: calc(var(--lynx-menu-radius) - .125rem); }
            .lynx-menu-dropdown .lynx-menu-item ul .lynx-menu-link:hover { background: color-mix(in srgb, currentColor 6%, transparent); }
            .lynx-menu-dropdown .lynx-menu-item ul .lynx-menu-section { color: var(--lynx-menu-muted); font-size: .8em; text-transform: uppercase; letter-spacing: .04em; cursor: default; }
        }

        /* Small screens: a panel from the top of the screen with every level listed. */
        @media (max-width: 47.99rem) {
            .lynx-menu-dropdown .lynx-menu-panel { box-sizing: border-box; inset: 0 0 auto 0; width: 100%; max-height: 100dvh; margin: 0; padding: 1rem 1.25rem 1.5rem; overflow-y: auto; color: var(--lynx-menu-fg); background: var(--lynx-menu-bg); border: 0; border-bottom: 1px solid var(--lynx-menu-border); box-shadow: var(--lynx-menu-shadow); }
            .lynx-menu-dropdown .lynx-menu-panel::backdrop { background: rgb(0 0 0 / .25); }
            .lynx-menu-dropdown .lynx-menu-close { display: block; margin-inline-start: auto; margin-bottom: .5rem; }
            .lynx-menu-dropdown .lynx-menu-items ul { padding-inline-start: var(--lynx-menu-indent); }
            .lynx-menu-dropdown .lynx-menu-level-0 > .lynx-menu-item + .lynx-menu-item { border-top: 1px solid var(--lynx-menu-border); }
            .lynx-menu-dropdown .lynx-menu-link, .lynx-menu-dropdown .lynx-menu-section { padding: .625rem 0; }
            .lynx-menu-dropdown .lynx-menu-section { color: var(--lynx-menu-muted); font-size: .85em; text-transform: uppercase; letter-spacing: .04em; }
        }

        @media (prefers-reduced-motion: reduce) { .lynx-menu-dropdown .lynx-menu-item > ul { transition: none; } }
    </style>
@endonce
<nav {{ $attributes->merge(['class' => trim('lynx-menu lynx-menu-dropdown '.$menu->class)]) }} aria-label="{{ $menu->title }}">
    <button type="button" class="lynx-menu-toggle" popovertarget="{{ $panelId }}">{{ __('Menu') }}</button>
    <div id="{{ $panelId }}" popover class="lynx-menu-panel">
        <button type="button" class="lynx-menu-close" popovertarget="{{ $panelId }}" popovertargetaction="hide">{{ __('Close') }}</button>
        @include('lynx-menus::menus.partials.list-items', ['items' => $menu->items, 'level' => 0, 'tag' => 'button'])
    </div>
</nav>
