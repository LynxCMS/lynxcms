---
title: Visitor Input
description: Check what visitors send before it reaches a selector, a view or the page cache, and what Lynx already checks for you.
---

# Visitor Input

Any input coming from an HTTP request—query parameters (`/courses?category=yoga`), search terms, or form submissions, should be untrusted. Lynx and Laravel handle transport-level safety (SQL parameter binding, HTML sanitization, output escaping) automatically, while your application code is responsible for validating domain constraints before interpolating user values into Lynx selectors.


## Security Boundaries: What Lynx Handles vs. What You Handle

| Layer | Handled automatically by Lynx / Laravel | Required in your application code |
| :--- | :--- | :--- |
| **Database queries** | Selector values are compiled to bound SQL parameters—input cannot execute arbitrary SQL. | Validate input before interpolating into selector strings so visitors cannot inject extra selector clauses (e.g., `, include=unpublished`). |
| **Forms** | `lynxcms/forms` validates field types, required rules, file MIME/extensions, spam checks, and rate limits (5 submissions / 10 min). | Add domain-specific Laravel validation rules where needed. |
| **Rich text** | HTML fields are sanitized on every write (admin, code, import, or MCP/AI agent) to strip scripts, styles, and unsafe URIs. | None for standard HTML fields. |
| **Uploads** | Image and file fields reject executable extensions (`.php`, `.html`, `.svg`, etc.). | None for standard file fields. |
| **Blade output** | `{{ }}`, `@field`, and `@string` HTML-escape output automatically. | Never pass raw visitor input to `{!! !!}`; use `@js()` when passing values to JavaScript. |

---

## Reading the query string

The query string is the part of an address after `?`. Read it with `request()`, and accept only the values you expect. For a number, `integer()` gives a whole number, or the default when the value isn't one:

```php
$pageNumber = max(1, request()->integer('page', 1));
```

For a choice, accept only the values the site has, read from where they are stored. A choice field's options are stored with the field, so `lynx()->fields->choices()` lists them. Their values stay the same in every language, while their labels are translated:

```php
$level = request()->query('level');
$level = in_array($level, lynx()->fields->choices('level')->values(), true) ? $level : null;
```

`in_array()` with `true` compares exactly, so an array sent as `?level[]=advanced`, or a value with extra characters, doesn't match. The visitor then sees the unfiltered list, which is what an address you don't recognize should show. Options that editors add to the field are accepted at once, with no change to your code.

Lists that grow, such as categories or tags, are usually pages, and a visitor names one by its page name. [Values in a selector](#values-in-a-selector) shows how to find it.

## Values in a selector

A selector is a small language, and a visitor's text can add conditions of its own: `?category=yoga, include=unpublished` (or `status=unpublished`) would list unpublished pages. Bound parameters keep the database safe, but they can't tell your conditions from the visitor's. Put a visitor's value in a selector only after checking that it is what you expect: a value from your list, a number, a date, or a page name.

The example below filters the courses listed on a `courses` page: `/courses?category=yoga&level=advanced&max_price=200`. Categories are pages on a `category` template, and each course links to its category with a [page field](/basics/field-types#page). A site with hundreds of categories in several languages works the same way, as the code never lists them. The example takes three steps.

**1. Check the query string.** Laravel's validator checks a set of values against [rules](https://laravel.com/docs/validation#available-validation-rules). Its `valid()` method returns the values that passed and leaves out the rest, so a value with a comma, an extra condition or an array never gets through. Unlike `$request->validate()`, it doesn't send the visitor back with errors, which suits filters in an address ([Laravel's docs](https://laravel.com/docs/validation#manually-creating-validators)).

**2. Find the category and build the selector.** The category is found by its page name in the visitor's language, so `/pt/cursos?category=ioga` finds the same category as `/courses?category=yoga`. A name that doesn't exist, or a category that isn't published, finds nothing, and the filter is left out. Then each value that passed becomes one condition, and the page's children are listed with them.

Both steps go in a view composer: a class that Laravel runs before it renders a view, to give the view more data ([Laravel's docs](https://laravel.com/docs/views#view-composers)). Lynx renders a `courses` page with the view `templates.courses` and gives it the page as `$page`, so the composer reads `$page` and adds `$courses`:

```php
// app/View/Composers/CourseFilters.php
namespace App\View\Composers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CourseFilters
{
    public function compose(View $view): void
    {
        $filters = Validator::make(request()->query(), [
            'category' => ['string', 'regex:/^[a-z0-9-]+$/'],
            'level' => [Rule::in(lynx()->fields->choices('level')->values())],
            'max_price' => ['integer', 'min:0'],
            'from' => ['date_format:Y-m-d'],
        ])->valid();

        // A category is a page: find it by its name in the visitor's language.
        $category = isset($filters['category'])
            ? lynx()->pages->findOne("template=category, name={$filters['category']}")
            : null;

        $conditions = array_filter([
            $category !== null ? "category={$category->id}" : null,
            isset($filters['level']) ? "level={$filters['level']}" : null,
            isset($filters['max_price']) ? "price<={$filters['max_price']}" : null,
            isset($filters['from']) ? "starts_on>={$filters['from']}" : null,
        ]);

        $page = $view->getData()['page'];

        $view->with('courses', $page->children(implode(', ', [...$conditions, 'sort=title'])));
    }
}
```

**3. Register the composer** for the template's view, in the `boot()` method of a service provider such as `app/Providers/AppServiceProvider.php`. The view then lists `$courses`:

```php
use App\View\Composers\CourseFilters;
use Illuminate\Support\Facades\View;

View::composer('templates.courses', CourseFilters::class);
```

```blade
{{-- resources/views/templates/courses.blade.php --}}
@foreach ($courses as $course)
    <a href="{{ $course->url }}">@field($course->title)</a>
@endforeach
```

Each rule fits one kind of value:

| Value | Rule | Accepts |
| --- | --- | --- |
| A page, such as a category or a tag | `regex:/^[a-z0-9-]+$/`, then `findOne()` | Lowercase letters, digits and hyphens, the characters of a page's URL name. The lookup then checks that the page exists and is published. |
| An option of a choice field | `Rule::in(lynx()->fields->choices('level')->values())` | The field's current options. |
| A number | `integer`, `min:0` | Whole numbers. `numeric` takes decimals. |
| A date | `date_format:Y-m-d` | Dates such as `2026-03-01`. |

`valid()` also returns query values that have no rule, such as `?utm_source=`, so build the selector from the keys you listed only, as the example does. A filter that fails its rule is left out, and the visitor sees the list without it. A single value can be checked without the validator, as [Reading the query string](#reading-the-query-string) shows. Words typed into a search box can't be checked against rules like these; quote them instead ([Words in a selector](#words-in-a-selector)). [Filters and pagination](/basics/selectors#filters-and-pagination) adds paging, and the [training site guide](/guides/training-site/front-end#the-catalog) has a complete catalog built this way.

### Words in a selector

Search words can't be checked against a list. Make them safe to quote instead: limit their length, remove double quotes, which would end the quoted value early, and put them inside double quotes. Inside double quotes, commas, pipes and single quotes are plain text:

```php
use Illuminate\Support\Str;

$query = request()->query('q');
$query = is_string($query) ? trim(Str::limit($query, 100, '')) : '';
$words = trim(str_replace('"', ' ', $query));

$results = $words === '' ? null : lynx()->pages->find('search*="'.$words.'", limit=30');
```

`is_string()` comes first because a visitor can send an array (`?q[]=dams`), and turning an array into text with `(string)` stops the page with an error. `limit=30` keeps a short word from listing the whole site. [Finding Pages](/basics/selectors#searching-text) explains how `search*=` matches words.

## Printing what visitors sent

Print visitor text with `{{ }}`, which escapes it, in the page and in attributes:

```blade
<input type="search" name="q" value="{{ $query }}">
<p>Results for “{{ $query }}”</p>
```

Never print it with `{!! !!}`, which prints HTML as it is. To hand a value to JavaScript, use Blade's `@js()`, which writes it as a safe JavaScript value:

```blade
<div x-data="{ query: @js($query) }">
```

## The page cache

When the [page cache](/modules/page-cache) is on, a page a guest sees can be stored and sent to the next guests. A page that reads the query string is stored once for each combination of the parameters its template lists, and rendered fresh for any other parameter, so a filter you forget to list is slower but never wrong. List only parameters whose values you check as above, since each value makes its own copy. [Query strings](/modules/page-cache#query-strings) explains the rules.
