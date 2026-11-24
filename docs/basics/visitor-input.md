---
title: Visitor Input
description: Check what visitors send before it reaches a selector, a view or the page cache, and what Lynx already checks for you.
---

# Visitor Input

Visitors send your site data all the time: words in a search box, a filter in the address (`/courses?category=yoga`), a form. Anything a visitor sends can be anything at all, so check it before you use it. Lynx and Laravel do much of the work. This page shows what they do for you, and the few places where checking is up to your code.

## What Lynx checks for you

- **Forms.** The [forms module](/modules/forms) checks every answer against its field: required fields, email addresses, lengths, the file types a `file` field takes, and any Laravel rules you add. It filters spam, and lets each visitor send a form five times in ten minutes.
- **Rich text.** The HTML of rich text fields is cleaned on every save, whoever sends it (an editor, your code, an import or an AI agent): text markup only, links to safe addresses only, and no scripts, styles or embedded content.
- **Files.** Image and file fields never accept files a browser or a web server would run, such as `.php`, `.html` or `.svg` ([Images and Files](/basics/images-and-files#adding-fields)).
- **Values in selectors.** A selector's values reach the database as bound parameters, never as SQL, so `title%=...` can't be turned into a database command.
- **Printing.** `{{ }}` and `@field` escape what they print, and so do the values `@string` puts in a text.

## Reading the query string

The query string is the part of an address after `?`. Read it with `request()`, and accept only the values you expect. For a number, `integer()` gives a whole number, or the default when the value isn't one:

```php
$pageNumber = max(1, request()->integer('page', 1));
```

For a choice, compare the value with the list you allow, and ignore anything else:

```php
$categories = ['yoga', 'pilates', 'nutrition'];
$category = request()->query('category');
$category = in_array($category, $categories, true) ? $category : null;
```

`in_array()` with `true` compares exactly, so an array sent as `?category[]=yoga`, or a value with extra characters, doesn't match. The visitor then sees the unfiltered list, which is what an address you don't recognize should show.

In a controller, Laravel's validation does the same with rules, such as `'page' => ['integer', 'min:1']`. Use whichever fits: the result is a value you know.

## Values in a selector

A selector is a small language, and a visitor's text can add conditions of its own: `?category=yoga, include=unpublished` (or `status=unpublished`) would list unpublished pages. Bound parameters keep the database safe, but they can't tell your conditions from the visitor's. Put a visitor's value in a selector only after checking that it is what you expect: a value from your list, a number, a date, or a page name.

The example below filters the courses listed on a `courses` page: `/courses?category=yoga&max_price=200`. It takes three steps.

**1. Check the query string.** Laravel's validator checks a set of values against [rules](https://laravel.com/docs/validation#available-validation-rules). Its `valid()` method returns the values that passed and leaves out the rest, so a value with a comma, an extra condition or an array never gets through. Unlike `$request->validate()`, it doesn't send the visitor back with errors, which suits filters in an address ([Laravel's docs](https://laravel.com/docs/validation#manually-creating-validators)).

**2. Build the selector** from the values that passed, one condition per filter, and list the page's children with it.

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
            'category' => [Rule::in(['yoga', 'pilates', 'nutrition'])],
            'max_price' => ['integer', 'min:0'],
            'from' => ['date_format:Y-m-d'],
            'teacher' => ['string', 'regex:/^[a-z0-9-]+$/'],
        ])->valid();

        $conditions = array_filter([
            isset($filters['category']) ? "category={$filters['category']}" : null,
            isset($filters['max_price']) ? "price<={$filters['max_price']}" : null,
            isset($filters['from']) ? "starts_on>={$filters['from']}" : null,
            isset($filters['teacher']) ? "teacher=/teachers/{$filters['teacher']}" : null,
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
| One of your options | `Rule::in([...])` | Only the listed values. |
| A number | `integer`, `min:0` | Whole numbers. `numeric` takes decimals. |
| A date | `date_format:Y-m-d` | Dates such as `2026-03-01`. |
| A page name | `regex:/^[a-z0-9-]+$/` | Lowercase letters, digits and hyphens, the characters of a page's URL name. |

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
