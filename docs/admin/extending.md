---
title: Extending the admin
description: Add screens and navigation, own a branch of the tree, change tree rows.
order: 3
---

# Extending the admin

## A new screen

```php
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Navigation;

Admin::routes(function (): void {
    // The view, for users with the permission; 403 for others.
    Admin::screen('reports', 'reports::index', 'reports.view')->name('reports'); // lynx.admin.reports
    Route::get('reports/{report}', ShowReportController::class)->name('reports.show');
});

app(Navigation::class)->add('Reports', 'lynx.admin.reports', order: 70, icon: 'chart-bar',
    visible: fn (): bool => app(AdminAccess::class)->allows('reports.view'));
```

Setup screens belong in a section of the sidebar: `group: Navigation::SETTINGS` (fields, templates, languages, SEO, sitemap) or `Navigation::ACCESS` (roles, users). Sections stay closed until opened, unless the current screen is in them, and each browser remembers the ones it opened. `order` then places the link within its section. Modules can add sections of their own with `app(Navigation::class)->group('reports', 'Reports', order: 70, icon: 'chart-bar')`.

`badge:` shows a count or short text beside the link, such as new form submissions; `0` and `null` hide it:

```php
app(Navigation::class)->add('Forms', 'lynx.admin.forms', icon: 'inbox',
    badge: fn (): int => Submission::query()->where('status', 'new')->count());
```

Links in the page editor's header for some pages, such as "Preview form" on form pages, come from `EditorLinks`:

```php
app(EditorLinks::class)->add(fn (Page $page): ?array => $page->template()->name === 'report'
    ? ['label' => 'Open report', 'url' => route('lynx.admin.reports', $page->id)]
    : null);
```

Tabs in the page editor come from `EditorTabs`. A tab shows after the template's tabs and before Settings, on the pages its closure accepts, and renders a Livewire component that receives the page's id as `pageId`. The page cache's Cache tab is one:

```php
app(EditorTabs::class)->add('reports', 'Reports', 'reports.page-tab',
    fn (Page $page): bool => $page->template()->hasUrl() && app(AdminAccess::class)->allows('reports.view'));
```

The key is the tab's value in the URL (`?tab=reports`). A template's own tab with the same key wins, and `content`, `texts` and `settings` are reserved.

A module that keeps its pages in a hidden branch registers it with `AdminBranches`: the page tree leaves the branch out, and the closure says where its pages open (`null` keeps a page in the page editor).

`Admin::routes()` adds the admin prefix, middleware and access gate. Route to `Admin::screen()` or controllers, not closures: `php artisan route:cache` stores a closure by its line, so a closure route written inside the `Admin::routes()` closure on one line comes back as the wrong closure, and the screen fails once routes are cached.

Wrap the view in the admin layout and use the BlatUI components:

```blade
<x-lynx-admin::layout title="Reports" :crumbs="['Pages' => route('lynx.admin.pages')]">
    <x-lynx-admin::ui.page-header title="Reports" description="What happened this month." />
    <livewire:reports />
</x-lynx-admin::layout>
```

Colours come from theme tokens (`bg-primary`, `text-muted-foreground`), never raw palette classes. Writes go through Lynx actions.

## A branch the module edits itself

A module that keeps its content as pages under a system page (as menus do under `/_menus/`) registers the branch. The page tree leaves it out, and the page editor sends its pages to the module's screen.

```php
app(\LynxCms\Admin\AdminBranches::class)->register('reports-root',
    fn ($page, $root): string => route('lynx.admin.reports', ['page' => $page->id]));
```

Give the root page the `system` status so it stays after the other pages.

## Page tree rows

Implement `LynxCms\Admin\Tree\TreePresenter` (`title()`, `detail()`) and pass it to `<livewire:lynx-admin.page-tree :presenter="MyPresenter::class" />`.
