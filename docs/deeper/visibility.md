---
title: Page access
description: Let a module stop visitors from opening some pages, and leave those pages out of what they find.
order: 7
---

# Page access

Lynx shows every published page to anyone. Modules that restrict pages, such as page passwords, use two hooks in the core. Without them registered nothing changes.

## Gates

A gate answers before a page renders. The first gate that returns a response is sent instead of the page; `null` lets the page through.

```php
use Illuminate\Http\Request;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;

app(PageGates::class)->add(fn (Page $page, Request $request) => $page->template()->name === 'report'
    ? response()->view('reports.locked', status: 403)
    : null);
```

## Visibility rules

A rule decides which pages visitors may see. It narrows selector queries with `apply()` and judges single pages with `allows()`; both must agree.

```php
use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VisibilityRule;

app(PageVisibility::class)->add(new class implements VisibilityRule
{
    public function apply(Builder $query): void { /* leave out what visitors may not see */ }

    public function allows(Page $page): bool { return true; }
});
```

While rules are active, every selector (`find`, `count`, `children`) and every page reference field leaves out the pages a rule refuses. `lynx()->pages->get()` is not filtered, so templates can still read known pages such as settings pages.

Rules apply to HTTP requests, including your own routes and front-end Livewire components. They do not apply:

- in the admin, whose routes carry the `WithoutPageVisibility` middleware;
- to the MCP web server and to agents;
- in Artisan commands, queue workers and the scheduler, so exports, backups, the sitemap and the search index see every page;
- inside `app(PageVisibility::class)->withoutRules(fn () => ...)`, for a route of yours that must see everything. Adding `WithoutPageVisibility` to the route does the same.
