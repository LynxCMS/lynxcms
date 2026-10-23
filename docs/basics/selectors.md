---
title: Finding Pages
description: Find, filter, sort and count pages with selectors, in any language.
---

# Finding Pages

Pages are not Eloquent models, so instead of a query builder you find them with **selectors**: short strings that filter, sort and limit pages, such as `template=post, sort=-published_on, limit=10`. A selector compiles to a single SQL query, reads the values visitors see in the current language, and leaves out pages the visitor may not see.

## Getting pages

The page API lives at `lynx()->pages`:

```php
// One page, by path, id or selector.
$about = lynx()->pages->get('/about');
$page = lynx()->pages->get(42);

// Every page that matches.
$posts = lynx()->pages->find('template=post, sort=-published_on, limit=10');

// The first page that matches, or null.
$latest = lynx()->pages->findOne('template=post, sort=-published_on');

// How many pages match.
$count = lynx()->pages->count('template=post');
```

Paths are written in the default language, whatever language the request is in. `get()` returns `null` when nothing matches. `find()` returns a `PageArray`, which you can loop, count, filter and join (see [Views](/basics/views#listing-pages)).

A page also finds its own children, with an optional selector:

```php
$page->children();
$page->children('template=post, sort=title');
```

## Writing selectors

A selector is a list of **clauses** separated by commas. Every clause must match. A clause is a name, an operator and a value:

```php
lynx()->pages->find('template=course, parent=/courses, title%=risk, price<300, sort=-title, limit=10');
```

The name can be a field of the page, such as `title` or `price`, or one of the page's own properties:

| Name | Matches |
| --- | --- |
| `template` | The page's template: `template=post`, `template=post|news`, `template!=page`. |
| `parent` | The page directly above, by path or id: `parent=/blog`. |
| `has_parent` | A page anywhere above: `has_parent=/projects` finds every page below `/projects`. |
| `id` | The page's id: `id=42`, `id!=1|2`. |
| `name` | The page's URL name in the current language: `name=about`. |
| `created`, `modified`, `published` | When the page was created, last saved or first published: `published>=-30 days`. |

### Operators

| Operator | Matches |
| --- | --- |
| `=` and `!=` | Equal and not equal. |
| `<`, `>`, `<=`, `>=` | Smaller and greater. |
| `%=` and `*=` | Contains. |
| `~=` | Contains every word, in any order: `summary~="risk management"` finds "management of risk". |
| `^=` | Starts with. |
| `$=` | Ends with. |

The text operators (`%=`, `*=`, `~=`, `^=` and `$=`) ignore case. Put a value in double quotes when it holds a comma or other special characters.

### Several values, empty values

`|` separates alternative values: `template=course|lesson` matches either. With `!=`, the page must differ from every value: `status!=draft|archived` finds neither.

An empty value means empty or missing: `summary=` finds pages without a summary, and `price!=` finds pages with a price.

### Several fields at once

`|` between field names matches when any of the fields matches:

```php
lynx()->pages->find('template=course, title|summary|body%=risk');
```

With `!=`, it matches when none of them holds the value.

## Dates

Date fields compare with dates, and with any words PHP understands as a date:

```php
lynx()->pages->find('template=event, starts_on>=today, sort=starts_on');
lynx()->pages->find('template=post, published_on>=2026-01-01, published_on<2027-01-01');
lynx()->pages->find('template=post, modified<-7 days');
```

A value without a time means midnight.

## Counting

`.count` compares how many children, references or items a page has:

```php
lynx()->pages->find('template=category, children.count>0');  // categories with pages
lynx()->pages->find('template=course, related.count>=2');
lynx()->pages->find('template=project, gallery.count=0');     // projects without images
```

`children.count` counts hidden and unpublished children, and leaves out drafts and system pages. Any field that holds several values can be counted: page references, choices with several values, repeaters, matrices, images and files. For items, only the ones switched on count.

## Inside repeaters, blocks and files

A dot looks inside the items of a [repeater or matrix](/basics/field-types#groups-of-fields). A page matches when one of its items, switched on, matches:

```php
lynx()->pages->find('template=project, credits.partner=/partners/acme');
lynx()->pages->find('credits.role%=direction');
lynx()->pages->find('blocks.type=video'); // matrix blocks of a type
```

`!=` finds pages where no item matches. Lists, such as page references, take `=` and `!=` only. Sorting by a field inside items is not supported.

[Images and files](/basics/images-and-files) work the same way, one item per file, leaving hidden files out. Files offer `file`, `mime`, `bytes`, `tags` and `description`; images add `width` and `height`:

```php
lynx()->pages->find('gallery.tags=hero');
lynx()->pages->find('hero.width>=1600');
lynx()->pages->find('downloads.mime=application/pdf');
lynx()->pages->find('blocks.gallery.count>0'); // inside matrix blocks
```

## Searching text

`search*=` looks for words in the search index, which holds the title of every page and the fields each template marks [searchable](/basics/templates-and-fields#making-fields-searchable):

```php
lynx()->pages->find('search*="opening hours", limit=20');
lynx()->pages->find('search*="seasons", template=project');
```

Every word must appear, as the beginning of a word: `estaç` finds "estações", but `ções` does not. Case and accents are ignored, so `estacoes` finds it too. `|` separates alternatives. Search uses full text indexes on SQLite (FTS5) and PostgreSQL.

## Languages

Selectors match the values visitors see in the current language, with fallback: a Portuguese visitor's `title%=risco` matches the Portuguese title, or the English one where there is no translation. Add `language=pt` to match another language than the request's:

```php
lynx()->pages->find('template=post, title%=risco, language=pt');
```

To work in a language for a whole block of code, use `lynx()->language()`:

```php
$posts = lynx()->language('pt', fn () => lynx()->pages->find('template=post, limit=5'));
```

## Status

Unpublished and hidden pages are left out. `include=hidden`, `include=unpublished` or `include=all` brings them back:

```php
lynx()->pages->find('template=post, include=all');
```

## Sorting and limits

`sort` takes a field or a page property; a `-` sorts descending. Several `sort` clauses sort by each in turn. `limit` caps the number of pages, and `start` skips some, for paging through results:

```php
lynx()->pages->find('template=post, sort=-published, sort=title, start=20, limit=10');
```

Without `sort`, pages come in the order editors gave them among their siblings (`sort=sort`), then by id. Besides fields, you can sort by `name`, `id`, `created`, `modified` and `published`.

### Selectors from visitors

A selector built from a request needs a cap, so a visitor can't ask for every page at once:

```php
lynx()->pages->find($request->string('q'), maxLimit: 50, defaultLimit: 20);
```

## Filters and pagination

A list that visitors filter, sort and page through is a controller like any other. Validate the query string, map each choice to a selector clause you wrote yourself, and keep visitors' text out of the selector's syntax. `start` and `limit` select one page of results, and `total()` counts every match, so Laravel's paginator can draw the links:

<<< @/snippets/advanced/PostIndexController.php#controller{php}

The view uses the paginator like any Eloquent one:

<<< @/snippets/advanced/views/blog/index.blade.php{blade}

Register the route in `routes/web.php` as usual, `Route::get('/posts', PostIndexController::class)`. Your routes come before Lynx's, so the controller answers `/posts` even if a page has that address.

## Which fields load

Pages found by a selector come with their names, status and title. Every other field loads the first time a template reads it, in one query for all the pages found together. Listing fifty courses and showing their summary costs one query for the summaries, not fifty, and fields a list never shows are never loaded.

When you know a list shows more fields, `join=` loads them with the pages:

```php
lynx()->pages->find('template=course, join=summary|price');
lynx()->pages->find('template=course, join=all');
```

A field that almost every list shows can load with every page instead: turn on **Load with the page** in the field's settings (`'autoload' => true` in code).

## Large sites

Sorting by a translatable field compares the value each language shows, fallback included. For the fields named in `lynx.sort_keys` (the title, by default), Lynx stores that value per page and language, so sorting reads an index. After adding a field to `sort_keys`, or changing a field's fallback setting, rebuild the keys:

```bash
php artisan lynx:sort-keys
```

On PostgreSQL, trigram indexes speed up `%=`, `*=`, `~=`, `^=` and `$=` on text fields. Install them, and set `LYNX_TRIGRAM=true` so new fields get them too:

```bash
php artisan lynx:trigram
```

Rare words become much faster to find (from 90 ms to 6 ms on 50,000 pages); words that match thousands of pages get a little slower (from 3 ms to 26 ms), and saving text costs a little more.

## Debugging

`lynx:find` runs a selector from the command line, and `--sql` prints the query it compiles to:

```bash
php artisan lynx:find "template=course, sort=title" --lang=pt --sql
```
