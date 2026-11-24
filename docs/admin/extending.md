---
title: Extending the admin
description: Add screens and navigation, own a branch of the tree, change tree rows.
order: 3
---

# Extending the admin

Your app can add its own screens to the admin, such as reports or a booking calendar, with links in its sidebar, add tabs to the page editor, and change how the page tree shows your pages. A screen is a Laravel route with a Blade view, and only users with the permission you name can open it.

Unless a section names another file, the PHP on this page registers things and goes in the `boot()` method of a service provider such as `app/Providers/AppServiceProvider.php` (or your module's own provider). A service provider is a Laravel class whose `boot()` method runs on every request, before your pages.

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

`shortcut:` is a letter that, after `g`, opens the screen from the keyboard, and the list of shortcuts (`?`) names it. Lynx uses `h`, `p`, `s`, `f` and `t`, and the menus and forms modules `m` and `i`:

```php
app(Navigation::class)->add('Reports', 'lynx.admin.reports', icon: 'chart-bar', shortcut: 'r');
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

Wrap the screen's view, such as `resources/views/admin/reports.blade.php`, in the admin layout and use the BlatUI components:

```blade
<x-lynx-admin::layout title="Reports" :crumbs="['Pages' => route('lynx.admin.pages')]">
    <x-lynx-admin::ui.page-header title="Reports" description="What happened this month." />
    <livewire:reports />
</x-lynx-admin::layout>
```

Colors come from theme tokens (`bg-primary`, `text-muted-foreground`), never raw palette classes. Writes go through Lynx actions.

When a list in a screen's view has nothing to show, say what would be there, how it gets there and, in the slot, offer the action that adds the first one. `compact` fits a panel or a narrow column:

```blade
<x-lynx-admin::empty-state icon="chart-bar" title="No reports yet." help="Reports you run show here.">
    <x-lynx-admin::ui.button :href="route('lynx.admin.reports.create')">New report</x-lynx-admin::ui.button>
</x-lynx-admin::empty-state>
```

When a filter or search found nothing, say so instead, and offer a button that clears it.

## Example: reviewing blog posts

This example adds a review step to the blog from [Your First Site](/getting-started/first-site). Editors who hold a new `blog.review` permission get three things. A **Review** screen lists the posts with something to fix, and its sidebar link shows how many there are. Each post gets a **Checklist** tab with a button that writes a missing summary. MCP clients and the agent get the same list as a [read tool](/ai/#your-own-read-tools). The test suite runs every file below.

Write the checks once, in `app/Blog/PostChecks.php`, a class that each part of the example uses:

<<< @/snippets/review/PostChecks.php#checks{php}

Register everything in a service provider, `app/Providers/BlogReviewServiceProvider.php`, listed in `bootstrap/providers.php`. The permission and the tool work without the admin, so the admin part only runs when the admin is installed:

<<< @/snippets/review/BlogReviewServiceProvider.php{php}

`Admin::screen()` renders the view only for users with `blog.review`; others get a 403. The screen's view, `resources/views/blog/review.blade.php`, wraps the list in the admin layout:

<<< @/snippets/review/views/blog/review.blade.php{blade}

The tab is a Livewire component. Livewire sends every click to its own endpoint, which is outside the admin's routes and their middleware. So the component checks access itself in `boot()`, which runs on every request, not only the first one. `AdminAccess::authorizeScreen()` checks the admin gate, then the permission you name, and answers 403 when either says no; `authorizeSuperuser()` does the same for screens only superusers use. The component goes in `app/Livewire/ChecklistTab.php`:

<<< @/snippets/review/ChecklistTab.php#tab{php}

Its view is `resources/views/blog/checklist-tab.blade.php`:

<<< @/snippets/review/views/blog/checklist-tab.blade.php{blade}

The button saves the post like any other write, so the save action checks `pages.edit` on that post. If a reviewer has `blog.review` but not `pages.edit`, they see the checklist, but the button gets a 403 and writes nothing. The change also shows in the post's history under the reviewer's name.

## Labels of the fields you install

A module that installs fields or templates can translate their labels for editors. Register the module's translation namespace in its service provider, and ship `lang/{locale}/schema.php` with the installed text as the key:

```php
use LynxCms\Core\Schema\SchemaWording;

$this->app->make(SchemaWording::class)->register('reports', templates: ['report'], fields: ['report_period']);
```

```php
// lang/pt/schema.php
return ['Period' => 'Período'];
```

Text the site rewords shows as the site wrote it. See [Labels in another language](/admin/#labels-in-another-language) for the labels of a site's own fields.

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

Such a module usually also keeps its templates out of the admin's general screens. Register these in the module's service provider:

```php
use LynxCms\Admin\Editor\BacklinkFilter;
use LynxCms\Admin\Editor\TemplateChoices;

// The New page screen doesn't offer these templates: the module's screen creates their pages.
app(TemplateChoices::class)->exclude('report', 'report-section');

// The References tab leaves out pages of these templates.
app(BacklinkFilter::class)->exclude('report-section');
```

## A marker in the page tree

A module shows a small icon beside the pages it changes, as page passwords show a key. The label is shown on hover and read by screen readers; return null for pages without the marker.

```php
use LynxCms\Admin\Tree\TreeMarkers;

app(TreeMarkers::class)->add('reviewed', fn ($page): ?array => $page->get('reviewed') === true
    ? ['icon' => 'badge-check', 'label' => 'Reviewed']
    : null);
```

## Files in the link dialog

The link dialog of rich text fields offers the files of the page being edited under **Select file**. A module that keeps files of its own adds them:

```php
use LynxCms\Admin\Editor\LinkFiles;

app(LinkFiles::class)->add(fn ($page): array => [
    ['name' => 'Annual report.pdf', 'url' => "/reports/{$page->id}/annual-report.pdf"],
]);
```

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

When the component changes the page's draft, tell the editor from the component's method, so it shows the new values:

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

## The home screen

The admin opens on a home screen: a welcome with buttons for common tasks, a row of stats, then two columns of panels. The main column has the pages with a draft and the next scheduled changes, the side column the recent changes. Modules and your app add to each part through `HomeScreen`, and each entry shows only to the users its `visible` closure accepts:

```php
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Home\HomeScreen;

$home = app(HomeScreen::class);
$canSee = fn (): bool => app(AdminAccess::class)->allows('reports.view');

// A number in the row of stats; a null value leaves it out.
$home->stat('reports', 'Reports this week', fn (): int => Report::thisWeek()->count(),
    url: fn (): string => route('lynx.admin.reports'), icon: 'chart-bar', visible: $canSee);

// A button beside the welcome.
$home->action('new-report', 'New report', fn (): string => route('lynx.admin.reports.create'),
    icon: 'plus', visible: $canSee);

// A panel in the main column, below Lynx's own lists.
$home->panel('reports::home-panel', $canSee, order: 30, key: 'reports');
```

A label can be a translation key. `order` places an entry among the others of its kind, lower first; entries with the same order keep the order they were added in. Lynx's own stats (pages, pages not published, languages) and actions (New page, Edit site texts) use 10 to 30, and the forms and menus modules add theirs at 30 and 40. Lynx's lists are panels with the orders 10 and 20.

A panel goes in the main column unless you pass `column: HomeScreen::SIDE`. Its `key` is the view's name unless you give one.

A stat is one number. A list, such as the most searched pages, is a panel. The panel's view gets no variables, so give it its data with a [view composer](https://laravel.com/docs/views#view-composers), a closure Laravel runs before it renders the view:

```php
View::composer('reports::home-panel', fn ($view) => $view->with('reports', Report::latest()->limit(5)->get()));
```

Build the panel's view (the one `panel()` names) from the home screen's own components, so it looks like Lynx's lists: a section with an icon, a heading linking to the full list, a count and an empty state, and a row per item, its title linking to it, details below and a date at the end:

```blade
<x-lynx-admin::home.section id="home-reports" icon="chart-bar" :href="route('lynx.admin.reports')"
    :title="'Reports'" :count="$reports->count()"
    :is-empty="$reports->isEmpty()" empty-icon="chart-bar"
    empty-title="No reports yet." empty-help="Reports you run show here.">
    @foreach ($reports as $report)
        <x-lynx-admin::home.row :url="route('lynx.admin.reports.show', $report)" :title="$report->name">
            {{ $report->summary }}
            <x-slot:aside>{{ $report->created_at->isoFormat('ll') }}</x-slot:aside>
        </x-lynx-admin::home.row>
    @endforeach
</x-lynx-admin::home.section>
```

`see-all-url` and `see-all-label` add a link to the full list, for when the panel shows only the first few.

### Arranging the home screen

Every entry has a key, so your app can take out or move what Lynx and the modules put on the home screen:

```php
$home = app(HomeScreen::class);

$home->remove('site-texts');                              // the Edit site texts button
$home->remove('recent');                                  // the recent changes
$home->move('forms', order: 5);                           // the forms module's panel, above the drafts
$home->move('upcoming', order: 5, column: HomeScreen::SIDE);
```

`remove()` and `move()` work on stats, actions and panels alike, whoever added them and in whichever order the service providers run: they apply when the screen is drawn. A key nothing has changes nothing, so removing what a module adds keeps working after the module is uninstalled. When the side column has nothing left, the main column takes the whole width.

| Key | What it is |
|---|---|
| `pages`, `unpublished`, `languages` | Lynx's stats |
| `new-page`, `site-texts` | Lynx's buttons |
| `drafts`, `upcoming` | Lynx's lists in the main column (orders 10 and 20) |
| `recent` | The recent changes, in the side column (order 10) |
| `forms`, `form-submissions` | The forms module's panel (order 30) and stat |
| `menus`, `finder` | The menus and finder modules' buttons |

Who sees an entry is still up to its `visible` closure. To show a panel only to some roles, register it with a closure that asks `AdminAccess`, as above.

### A panel with data from another service

A panel is drawn with the home screen, so anything slow in it makes the whole screen slow, and an error in it can break the screen. When the data comes from another service, put it in a [Livewire](https://livewire.laravel.com/docs/lazy) component that loads after the page, cache what the service answers, and show a notice when it does not answer.

Here is a panel with the weather for the next three days from [Open-Meteo](https://open-meteo.com/), which needs no key. The site's coordinates go in `config/services.php`:

```php
'open_meteo' => [
    'latitude' => env('OPEN_METEO_LATITUDE', 38.72),
    'longitude' => env('OPEN_METEO_LONGITUDE', -9.14),
],
```

The component, in `app/Livewire/WeatherPanel.php`, asks Open-Meteo at most every 30 minutes, waits 3 seconds at most, and reports a failure without caching it, so the next visit tries again:

```php
namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

final class WeatherPanel extends Component
{
    public const string CACHE_KEY = 'home.weather';

    private const string ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    private const int CACHE_MINUTES = 30;

    private const int TIMEOUT_SECONDS = 3;

    private const int DAYS = 3;

    public function placeholder(): View
    {
        return view('livewire.weather-panel-placeholder');
    }

    public function render(): View
    {
        return view('livewire.weather-panel', ['days' => $this->forecast()]);
    }

    /** @return list<array{date: string, code: int, max: float, min: float}>|null null when Open-Meteo did not answer */
    private function forecast(): ?array
    {
        $days = Cache::get(self::CACHE_KEY);

        if (is_array($days)) {
            return $days;
        }

        $days = $this->fetch();

        if ($days !== null) {
            Cache::put(self::CACHE_KEY, $days, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $days;
    }

    /** @return list<array{date: string, code: int, max: float, min: float}>|null */
    private function fetch(): ?array
    {
        try {
            $daily = Http::timeout(self::TIMEOUT_SECONDS)->get(self::ENDPOINT, [
                'latitude' => config('services.open_meteo.latitude'),
                'longitude' => config('services.open_meteo.longitude'),
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone' => 'auto',
                'forecast_days' => self::DAYS,
            ])->throw()->json('daily');
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return null;
        }

        return array_map(fn (int $day): array => [
            'date' => $daily['time'][$day],
            'code' => (int) $daily['weather_code'][$day],
            'max' => (float) $daily['temperature_2m_max'][$day],
            'min' => (float) $daily['temperature_2m_min'][$day],
        ], array_keys($daily['time']));
    }
}
```

Its view, `resources/views/livewire/weather-panel.blade.php`, uses the home screen's section, so the panel has the same heading and empty state as Lynx's lists. Open-Meteo gives each day a [WMO weather code](https://open-meteo.com/en/docs#weather_variable_documentation), which the view turns into words and a [Lucide](https://lucide.dev/icons/) icon:

```blade
@php
    $kinds = [95 => ['Thunderstorms', 'cloud-lightning'], 80 => ['Showers', 'cloud-rain'], 71 => ['Snow', 'cloud-snow'],
        61 => ['Rain', 'cloud-rain'], 51 => ['Drizzle', 'cloud-drizzle'], 45 => ['Fog', 'cloud-fog'],
        1 => ['Some clouds', 'cloud-sun'], 0 => ['Clear', 'sun']];
    $kind = fn (int $code): array => collect($kinds)->first(fn (array $kind, int $from): bool => $code >= $from);
@endphp
<x-lynx-admin::home.section id="home-weather" data-home-panel="weather" icon="cloud-sun" title="Weather"
    :is-empty="$days === null" empty-icon="cloud-off"
    empty-title="The forecast is not available." empty-help="Open-Meteo did not answer. The panel tries again on the next visit.">
    @foreach ($days ?? [] as $day)
        <li class="flex items-center gap-3 border-b py-2.5 text-sm">
            <x-lynx-admin::ui.icon :name="$kind($day['code'])[1]" class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
            <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($day['date'])->isoFormat('dddd') }}</span>
            <span class="text-muted-foreground">{{ $kind($day['code'])[0] }}</span>
            <span class="ms-auto tabular-nums">{{ round($day['max']) }}° <span class="text-muted-foreground">{{ round($day['min']) }}°</span></span>
        </li>
    @endforeach
</x-lynx-admin::home.section>
```

`resources/views/livewire/weather-panel-placeholder.blade.php` is what shows while the forecast loads:

```blade
<section class="grid gap-3" aria-busy="true">
    <h2 class="text-base font-semibold">Weather</h2>
    <div class="bg-muted h-24 animate-pulse rounded-md"></div>
</section>
```

The panel itself is a view with only the component in it, `resources/views/admin/home-weather.blade.php`:

```blade
<livewire:weather-panel lazy />
```

Register it in a service provider's `boot()`, here in the side column below the recent changes:

```php
app(HomeScreen::class)->panel('admin.home-weather', key: 'weather', column: HomeScreen::SIDE, order: 20);
```

The home screen now shows at once with the placeholder, and the forecast fills in when Open-Meteo answers. While the cache holds it, the panel shows it without asking Open-Meteo again.

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

## Hints

A hint is a small help icon beside a heading or a label. It explains the thing in a tooltip, so the screen keeps only what people need every time. The editor's Settings tab uses one beside **Names and languages** to say how a cleared URL name fills again. Put one in a screen's view or a Livewire component's view:

```blade
<div class="flex items-center gap-2">
    <h2 id="reports-schedule" class="text-base font-semibold">{{ __('reports::admin.schedule') }}</h2>
    <x-lynx-admin::hint :label="__('reports::admin.schedule_hint_label')">
        {{ __('reports::admin.schedule_hint') }}
    </x-lynx-admin::hint>
</div>
```

The hint opens on hover, on keyboard focus and on a tap, since touch screens have no hover, and Escape closes it. Its icon is a button named by `label`, and the text describes it, so screen readers read both. Write the label as the question the hint answers ("How names fill again"), and keep the text to a few sentences: the tooltip is narrow, and anything longer belongs on the screen or in the docs.

| Attribute | What it does |
|---|---|
| `label` | The button's name for screen readers. Required. |
| `side` | Where the tooltip opens: `top`, `right`, `bottom` (the default) or `left`. |

Other attributes go on the hint's wrapper, so `data-*` attributes and classes work as on any element. The icon takes the site's accent color at 80%, lighter in dark mode, as the home screen's icons do. Its classes are in the admin's stylesheet already, so a module needs nothing in its own.

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

### Help under a field

Give the help text under a field the `lynx-field-help` class, so it has the same size and color as the admin's own: a step smaller than the field's text, in the muted color.

```blade
<label for="reports-title" class="text-sm font-medium">{{ __('reports::admin.title') }}</label>
<input id="reports-title" type="text" class="blat-input" wire:model="title">
<p class="lynx-field-help">{{ __('reports::admin.title_help') }}</p>
```

The class is in the admin's own stylesheet, so a module doesn't need it in its own. For help that only some people need, use a [hint](#hints) beside the label instead.

### Error messages

Give an error message `data-error-for` with the error's key, and `role="alert"` so it is read out when it appears:

```blade
@error('title') <p data-error-for="title" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
```

The admin ties the message to the field bound to that key in the same component (its `wire:model`, or its `name` in a plain form): the field gets `aria-describedby` pointing to the message and `aria-invalid`, so a screen reader on the field reads why it is invalid. A key for a whole field (`values.title`) ties the message to each of its inputs (`values.title.en`, `values.title.pt`). It happens in the browser and again after each Livewire update, so the view needs nothing else.

### Keeping the site's look

Your classes read the same variables as the admin's, so a site's appearance settings and branding (accent color, base color, radius, fonts, spacing, dark mode) apply to your screens too. Three things keep it that way:

- Don't define tokens in your stylesheet (`--primary`, `--radius`, `--spacing`…). `module.css` defines none, and anything you add would override the site's settings. Tailwind's own `--tw-` variables are fine.
- Use the admin's color classes (`bg-primary`, `text-muted-foreground`), not Tailwind's palette (`bg-blue-500`), which `module.css` leaves out.
- Use the admin's form components (`<x-lynx-admin::ui.input>`, `ui.textarea`, `ui.select`, `ui.checkbox`, `ui.switch`), or give a native control BlatUI's class for it (`blat-input`, `blat-textarea`, `blat-select`, `blat-checkbox`, `blat-radio`). Both follow the site's input style (outline, fill or inset); a control with classes of your own does not.
