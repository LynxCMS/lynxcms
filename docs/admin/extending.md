---
title: Extending the admin
description: Add screens and navigation, own a branch of the tree, change tree rows.
order: 3
---

# Extending the admin

Your app can add its own screens to the admin, such as reports or a booking calendar, with links in its sidebar, add tabs to the page editor, and change how the page tree shows your pages. A screen is a Laravel route with a Blade view, and only users with the permission you name can open it.

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

A label can be a translation key, such as `'reports::nav.reports'`, so the link follows [the admin's language](/admin/#the-admin-s-language).

`badge:` shows a count or short text beside the link, such as new form submissions; `0` and `null` hide it:

```php
app(Navigation::class)->add('Forms', 'lynx.admin.forms', icon: 'inbox',
    badge: fn (): int => Submission::query()->where('status', 'new')->count());
```

Links under the page's title in the editor for some pages, such as "Preview form" on form pages, come from `EditorLinks`:

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

## Example: reviewing blog posts

This example adds a review step to the blog from [Your First Site](/getting-started/first-site). Editors who hold a new `blog.review` permission get three things. A **Review** screen lists the posts with something to fix, and its sidebar link shows how many there are. Each post gets a **Checklist** tab with a button that writes a missing summary. MCP clients and the agent get the same list as a [read tool](/ai/#your-own-read-tools). The test suite runs every file below.

Write the checks once, in a class that each part of the example uses:

<<< @/snippets/review/PostChecks.php#checks{php}

Register everything in a service provider. The permission and the tool work without the admin, so the admin part only runs when the admin is installed:

<<< @/snippets/review/BlogReviewServiceProvider.php{php}

`Admin::screen()` renders the view only for users with `blog.review`; others get a 403. The screen's view wraps the list in the admin layout:

<<< @/snippets/review/views/blog/review.blade.php{blade}

The tab is a Livewire component. Livewire sends every click to its own endpoint, which is outside the admin's routes and their middleware. So the component checks access itself in `boot()`, which runs on every request, not only the first one:

<<< @/snippets/review/ChecklistTab.php#tab{php}

<<< @/snippets/review/views/blog/checklist-tab.blade.php{blade}

The button saves the post like any other write, so the save action checks `pages.edit` on that post. If a reviewer has `blog.review` but not `pages.edit`, they see the checklist, but the button gets a 403 and writes nothing. The change also shows in the post's history under the reviewer's name.

## Field types the field form offers

The New field screen asks what a field holds, and lists each type by a name for people ("Paragraphs", "Blocks"), in a group, with an icon and one sentence. Give your type the same, or it shows its type name under **Other**:

```php
app(\LynxCms\Admin\Editor\FieldKinds::class)->register('rating', 'Star rating', 'Choices', 'star', 'One to five stars.');
```

The icon is a [Lucide](https://lucide.dev/icons) name. The groups are Text, Numbers and dates, Choices, Pages and media, and Structure; a group of your own comes after them.

A module whose field type only its own schema creates can leave it out of the choices, as the forms module does with `options`. The type still works through the API and MCP, and existing fields of it still open in the field form.

```php
app(\LynxCms\Admin\Editor\FieldTypeChoices::class)->exclude('rating');
```

## A branch the module edits itself

A module that keeps its content as pages under a system page (as menus do under `/_menus/`) registers the branch. The page tree leaves it out, and the page editor sends its pages to the module's screen.

```php
app(\LynxCms\Admin\AdminBranches::class)->register('reports-root',
    fn ($page, $root): string => route('lynx.admin.reports', ['page' => $page->id]));
```

Give the root page the `system` status so it stays after the other pages.

## Page tree rows

Implement `LynxCms\Admin\Tree\TreePresenter` (`title()`, `detail()`) and pass it to `<livewire:lynx-admin.page-tree :presenter="MyPresenter::class" />`.

## A button in the page editor

A module can put its own Livewire component in the page editor's header, beside the save buttons, for example a button that translates the page. Register it in a slot from your service provider's `boot()`:

```php
use Livewire\Livewire;
use LynxCms\Admin\AdminSlots;
use LynxCms\Core\Pages\Page;

Livewire::component('translate-button', TranslateButton::class);

app(AdminSlots::class)->add(
    AdminSlots::EDITOR_HEADER,
    'translate-button',
    fn (Page $page): bool => $page->template()->name !== 'form',
);
```

The editor mounts the component with the page's id as `pageId`. It shows the slot to people who may edit the page, on its content tabs, and skips the component on pages where the last argument returns `false`. Leave that argument out to show it on every page.

When the component changes the page's draft, tell the editor, so it shows the new values:

```php
$this->dispatch('lynx-draft-changed');
```

The editor reloads the draft and puts back whatever the editor typed and has not saved yet.

### On the Site texts screen

`AdminSlots::SITE_TEXTS_HEADER` shows components beside the Site texts screen's title, each mounted with the home page's id as `pageId`, where site texts are kept.

### Under each field

A slot can also hold a Blade view, which costs less than a component when it repeats. The editor shows the views of `editor.field` under each field's input, once per language:

```php
app(AdminSlots::class)->addView(AdminSlots::EDITOR_FIELD, 'my-module::field-note');
```

The view gets `$page`, `$field` (the field's name), `$language` (the language's code) and `$isTranslatable`. To act on the page, it can dispatch a browser event to a component you put in the header.

### Below a tab's fields

Each tab that holds fields has its own slot, `AdminSlots::editorTab()` with the tab's key. Its views show below the tab's fields, in the main column. The SEO module shows its preview of the page on the SEO tab this way:

```php
app(AdminSlots::class)->addView(AdminSlots::editorTab('seo'), 'my-module::seo-note',
    fn (Page $page): bool => $page->template()->hasUrl());
```

The view gets `$page` and `$tab`. It renders again each time the editor does, so after a save it shows the saved page.

## Page tree filters

The page tree's Details view lists filters such as "Has a draft". A module can add its own, each a [selector](/basics/selectors) clause over the site's pages:

```php
use LynxCms\Admin\Tree\TreeFilterRegistry;

app(TreeFilterRegistry::class)->add(fn (): array => [
    'needs-review' => ['label' => 'Needs review', 'term' => 'reviewed=0', 'hint' => 'Pages nobody has reviewed yet.'],
]);
```

The `hint` is optional: it shows when someone hovers over the filter or focuses it, and says what the filter finds.

The closure runs when the tree shows its filters, so it can read the site's languages or settings. Lynx's own filters keep their keys, so a module filter with the same key is left out.

## Styles for your screens

The admin's stylesheet is built from Lynx's own views, so a class only your module uses doesn't exist in it. A module in its own repository builds a small stylesheet of its own, with the admin's classes and tokens, and registers it.

Start the stylesheet from the one the admin ships for modules, and point Tailwind at your views:

```css
/* resources/css/admin.css */
@import "../../vendor/lynxcms/admin/resources/css/module.css";
@source "../views";
```

Build it with the same Tailwind major version as the admin (4), and commit the result with your package:

```bash
npm install --save-dev tailwindcss @tailwindcss/cli
npx tailwindcss -i resources/css/admin.css -o dist/admin.css --minify
```

Then register it from your service provider's `boot()`:

```php
use LynxCms\Admin\AdminAssets;

app(AdminAssets::class)->stylesheet('reports', __DIR__.'/../dist/admin.css');
```

Every admin page then links it after the admin's own stylesheet and before the site's (`lynx-admin.stylesheets`), with a version that changes with the file, so browsers never keep an old copy.

### Keeping the site's look

Your classes read the same variables as the admin's, so a site's appearance settings and branding (accent colour, base colour, radius, fonts, spacing, dark mode) apply to your screens too. Three things keep it that way:

- Don't define tokens in your stylesheet (`--primary`, `--radius`, `--spacing`…). `module.css` defines none, and anything you add would override the site's settings. Tailwind's own `--tw-` variables are fine.
- Use the admin's colour classes (`bg-primary`, `text-muted-foreground`), not Tailwind's palette (`bg-blue-500`), which `module.css` leaves out.
- Use the admin's form components (`<x-lynx-admin::ui.input>`, `ui.textarea`, `ui.select`, `ui.checkbox`, `ui.switch`), or give a native control BlatUI's class for it (`blat-input`, `blat-textarea`, `blat-select`, `blat-checkbox`, `blat-radio`). Both follow the site's input style (outline, fill or inset); a control with classes of your own does not.
