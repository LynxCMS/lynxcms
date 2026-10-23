---
title: Menus
description: Navigation menus built in the admin and rendered with one Blade component.
order: 2
---

# Menus

Menus come with `lynxcms/menus`. A menu is a set of pages under the system page `/_menus/`, edited in the admin's **Menus** screen. Items link to a page (optionally with its children), link to a URL, or are section labels. Labels and URLs can differ per language.

## Rendering

```blade
<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />
```

| View | Style |
| --- | --- |
| `lynx-menus::menus.list` | Nested list. |
| `lynx-menus::menus.dropdown` | Opens on hover. |
| `lynx-menus::menus.accordion` | Opens on click. |

Any view of your own works: it receives the menu's items as a tree. Menus leave out pages that are hidden, unpublished, below an unpublished page, or not published in the current language.

## Stored menus

A menu is stored built, per language: its items, labels, URLs and child pages. Visitors read it with one query; the link to the current page is marked on every request. The first visit after a change builds it again. A change drops it when it touches a page the menu shows or depends on: the menu and its items, the pages it links (shown or hidden), the child pages it lists and the pages above them. Adding a language, or changing fields and templates, drops every menu.

Visitors who see more than everyone else, such as one who unlocked a password protected page or a signed-in editor allowed to see it, get the menu built for them, and nothing is stored.

Changes made outside Lynx, such as pages edited with SQL, need a rebuild: the **Rebuild** button in the menu editor, or the `menus.rebuild` action (one menu, or every menu without one):

```php
lynx()->run(LynxCms\Menus\Actions\RebuildMenus::class, ['menu' => 'top']);
```

To skip the rendering too, keep the menu's HTML in a fragment with `lynxcms/cache`. The link to the current page carries `aria-current="page"`, so vary by page:

```blade
@lynxCache('top-menu', ['vary' => [$page->id]])
    <x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />
@endLynxCache
```

## In code

```php
lynx()->menus()->create('top', ['default' => 'Main navigation', 'pt' => 'Navegação principal']);
lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/', 'children' => true]);
lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'GitHub', 'url' => 'https://github.com', 'target' => '_blank']);

$menu = lynx()->menus()->get('top');
```

Items also take `icon_before`, `icon_after` and `languages` (the languages an item shows in).

Links follow the same rule as links in rich text. A link that opens a new window gets `rel="noopener"`. Editors can add `nofollow`, `sponsored` or `noreferrer` (the choices in `lynx-admin.links.rels`), in the item form or with `'rel' => ['sponsored']`.

## Permissions

Managing menus needs `menus.manage`. Roles that grant it must grant `pages.view` too, so editors can pick pages.
