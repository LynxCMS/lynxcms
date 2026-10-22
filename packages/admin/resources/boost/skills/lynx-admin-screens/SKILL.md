---
name: lynx-admin-screens
description: Extend the Lynx admin with new screens, navigation items and page tree presenters, built from the admin's BlatUI components. Use when adding a module's admin screen or changing how the admin looks.
---

# Lynx admin screens

The admin is Livewire on BlatUI components, used as `<x-lynx-admin::ui.button>`, `<x-lynx-admin::ui.card>`, `<x-lynx-admin::ui.table>` and so on. Use them for new screens; colours come only from theme tokens (`bg-primary`, `text-muted-foreground`), never raw palette classes.

## A new screen

```php
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Navigation;

Admin::routes(function (): void {
    Route::get('reports', ReportsController::class)->name('reports'); // route name lynx.admin.reports
});

app(Navigation::class)->add('Reports', 'lynx.admin.reports', order: 70, icon: 'chart-bar',
    visible: fn (): bool => app(AdminAccess::class)->allows('reports.view'));
```

Setup screens go in a sidebar section: add `group: Navigation::SETTINGS` or `group: Navigation::ACCESS`.

Wrap the view in the admin layout:

```blade
<x-lynx-admin::layout title="Reports" :crumbs="['Pages' => route('lynx.admin.pages')]">
    <x-lynx-admin::ui.page-header title="Reports" description="What happened this month." />
    <livewire:reports />
</x-lynx-admin::layout>
```

- Register the permission your screen needs: `lynx()->permissions()->register('reports.view', 'View reports')`.
- Writes from the screen go through Lynx actions, never directly to the database.
- Look and feel: `config('lynx-admin.appearance')` (base, theme, radius, font…) and BlatUI tokens in a stylesheet listed in `lynx-admin.stylesheets`.

## A branch the module edits itself

If the module keeps its content as pages under a system page (as menus do under `/_menus/`), register the branch: the site's page tree leaves it out, and the page editor sends its pages to your screen.

```php
app(\LynxCms\Admin\AdminBranches::class)->register('reports-root', fn ($page, $root): string => route('lynx.admin.reports', ['page' => $page->id]));
```

Give the branch's root page the `system` status so it stays after the other pages.

## Page tree rows

Implement `LynxCms\Admin\Tree\TreePresenter` (`title()`, `detail()`) and pass it to `<livewire:lynx-admin.page-tree :presenter="MyPresenter::class" />` to change how rows read.
