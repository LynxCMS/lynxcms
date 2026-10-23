# lynxcms/menus

> [!NOTE]
> This is a read-only split of [`packages/menus`](https://github.com/LynxCMS/lynxcms/tree/main/packages/menus) in the [Lynx CMS monorepo](https://github.com/LynxCMS/lynxcms). Open issues and pull requests there. Lynx is not ready for production yet: see its README.

Multilingual menus for Lynx CMS. Build as many menus as you need (a top bar, a footer, a legal strip) and render each one with the Blade view you want: a hover dropdown, a click accordion, or markup of your own.

Menus are stored as pages (ADR-0008, ADR-0025), so they get translations, fallback, ordering, the activity log and MCP and AI tools for free.

## Installing

```bash
composer require lynxcms/menus
php artisan lynx:migrate   # asks whether the database is backed up
```

See the [installation guide](https://github.com/LynxCMS/lynxcms/blob/main/docs/getting-started/installation.md).

## Items

| Kind | What it is | Label |
| --- | --- | --- |
| Section label | Text without a link, used to group items | Required, per language |
| Page | Any page with a URL. Optionally adds the page's child pages automatically | Optional, falls back to the page title in each language |
| Custom link | A URL per language (relative, `#fragment`, http(s), `mailto:`, `tel:`) with an optional target (`_blank`, `_self`, `_parent`, `_top` or a frame name) | Required, per language |

Every item can carry CSS classes and an icon before and after its label. Items nest without limit. Links that open a new window get `rel="noopener"`; editors can add `nofollow`, `sponsored` or `noreferrer`.

An item shows in every language unless you pick the languages it shows in (`languages`), so a link can appear only in Portuguese.

## What visitors see

A page item, and every child page added automatically, is left out when the page:

| Page state | In the menu |
| --- | --- |
| Hidden | Left out (hidden pages stay reachable by URL, but are kept out of listings and menus) |
| Unpublished, or below an unpublished page | Left out (the whole branch is offline, ADR-0026) |
| Not available in the current language | Left out, except in the default language, which always serves every page |

These checks cost one query per menu, and the URLs of all linked pages are loaded together.

Each menu has a title per language (its accessible name) and CSS classes.

A page item that shows its child pages sets how many levels to add (`depth`, 1 to 10, default 1) and how many child pages per level (`limit`, 1 to 100, default 10). Both are per item, so "Courses" can go two levels deep with eight pages while "Blog" shows one level.

## Editing

With `lynxcms/admin` installed, the admin gets a **Menus** screen: a list of menus, a tree of the chosen menu's items (drag and drop or keyboard, like the page tree) and forms for items and menu settings.

From code:

```php
lynx()->menus()->create('top', ['default' => 'Main navigation', 'pt' => 'Navegação principal']);

lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/', 'children' => true, 'depth' => 2, 'limit' => 8]);
$resources = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['default' => 'Resources', 'pt' => 'Recursos']]);
lynx()->menus()->saveItem(['menu' => 'top', 'parent' => $resources->id, 'kind' => 'link', 'label' => 'Laravel', 'url' => 'https://laravel.com', 'target' => '_blank', 'icon_after' => 'icon-external']);
lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['pt' => 'Novidades'], 'languages' => ['pt']]);
```

The same operations are the actions `menus.create` and `menus.save_item`, so MCP clients and the built-in agent can build menus. Sort, move and delete items with `pages.sort`, `pages.move` and `pages.delete`.

## Rendering

```blade
<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />
<x-lynx-menu name="footer" view="lynx-menus::menus.accordion" class="site-footer-menu" />
```

| View | Behaviour |
| --- | --- |
| `lynx-menus::menus.list` (default) | Plain nested `<ul>`, no styles, no JavaScript |
| `lynx-menus::menus.dropdown` | Horizontal bar; submenus open on hover and on keyboard focus (CSS only). Below 48rem a "Menu" button opens the whole menu as a panel (HTML `popover`, no JavaScript; Esc and tapping outside close it) |
| `lynx-menus::menus.accordion` | Vertical; branches open on click with `<details>`, and the branch holding the current page starts open |

The dropdown's button texts are the Laravel translation keys `Menu` and `Close` (add them to `lang/pt.json` and so on). To change the 48rem breakpoint, publish the view.

Restyle the bundled views with their `--lynx-menu-*` CSS variables, publish them with `php artisan vendor:publish --tag=lynx-menus-views`, or pass any view of your own. A view receives `$menu` and `$attributes`:

```blade
<nav {{ $attributes }} aria-label="{{ $menu->title }}">
    @foreach ($menu->items as $item)
        <a href="{{ $item->url }}" @class(['active' => $item->isInActiveTrail])>{{ $item->label }}</a>
        {{-- $item->children, $item->hasUrl(), $item->target, $item->rel, $item->class, $item->depth --}}
    @endforeach
</nav>
```

For JavaScript frontends, `lynx()->menus()->get('top')?->toArray()` returns the same tree as an array.

### Icons

By default an icon value is a list of CSS classes for an icon font, rendered as `<span class="lynx-menu-icon lynx-menu-icon-before fa fa-home" aria-hidden="true"></span>`. To use SVG icons instead, register a renderer once, for example in a service provider:

```php
lynx()->menus()->renderIconsUsing(fn (string $icon, string $position) => svg($icon, 'size-4')); // blade-icons
```

The renderer's output is printed as HTML, so only return markup you trust. Custom views read `$item->iconBefore` and `$item->iconAfter`.

`$item->isActive` marks the item whose URL is the current page, and `$item->isInActiveTrail` marks it and every item above it.
