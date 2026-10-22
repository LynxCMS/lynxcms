---
name: lynx-menus
description: Render and manage Lynx menus. Use when adding a navigation menu to a layout, choosing or writing a menu view, or creating menus and items in code.
---

# Lynx menus

Menus are pages under `/_menus/`, edited in the admin's Menus screen. Items link to a page (optionally with its children), to a URL, or are section labels; labels and URLs can differ per language.

## Rendering

```blade
<x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />
```

Bundled views: `lynx-menus::menus.list`, `lynx-menus::menus.dropdown` (hover), `lynx-menus::menus.accordion` (click). Any view of your own works: it gets the menu's items as a tree.

## In code

```php
lynx()->menus()->create('top', ['default' => 'Main navigation', 'pt' => 'Navegação principal']);
lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/', 'children' => true]);
lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'GitHub', 'url' => 'https://github.com', 'target' => '_blank']);

$menu = lynx()->menus()->get('top'); // read side, items already resolved for the current language
```

Managing menus needs the `menus.manage` permission, and roles granting it must grant `pages.view` too.
