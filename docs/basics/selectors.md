---
title: Finding Pages
description: Find, filter, sort and count pages with selectors, in any language.
---

# Finding Pages

You find pages with **selectors**: short strings that say which pages you want, in what order and how many. A selector reads almost like a sentence. `template=post, sort=-published_on, limit=10` is "posts, newest first, at most ten".

Each selector runs as a single database query. It compares the values visitors see in their language, and leaves out pages they may not see, such as unpublished ones, so your code doesn't check either. Pages aren't Eloquent models, so selectors take the place of Laravel's query builder for them.

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
| `%=` and `*=` | Contains (two spellings of the same operator). |
| `~=` | Contains every word, in any order: `summary~="risk management"` finds "management of risk". |
| `^=` | Starts with. |
| `$=` | Ends with. |

The text operators (`%=`, `*=`, `~=`, `^=` and `$=`) ignore case. Put a value in double quotes when it holds a comma or other special characters.

### Several values, empty values

`|` separates alternative values: `template=course|lesson` matches either. With `!=`, the page must differ from every value: `category!=news|events` finds pages in neither category.

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

`language!=pt` does something else: it finds the pages that aren't published in Portuguese yet, which is a translator's to-do list. Values still match in the request's language. Every page is published in the default language, so `language!=en` on an English site finds nothing.

```php
$untranslated = lynx()->pages->find('template=post, language!=pt');
```

## Status

Unpublished and hidden pages are left out. `include=hidden`, `include=unpublished` or `include=all` brings them back:

```php
lynx()->pages->find('template=post, include=all');
```

`status` finds pages by their status instead: `status=unpublished`, `status=hidden|unpublished` or `status=locked`. Naming a status brings those pages back on its own, so `include` isn't needed. `status!=locked` leaves locked pages out.

Two more clauses ask about a page's versions. `has_draft=1` finds pages with changes saved as a draft and not yet published, and `has_schedule=1` finds pages with a publish or unpublish date waiting ([Scheduled publishing](/basics/publishing#scheduled-publishing)). `=0` finds the others.

```php
$waiting = lynx()->pages->find('has_draft=1, sort=-modified');
```

### Pages without a URL

Pages whose template has no URL, such as hero slides, promos, forms or a page of site settings, only show inside other pages, so selectors leave them out. A search for "agents" finds the product and the news, not the slide that mentions them.

Ask for them and you get them: name their template, read below one of them, or name them by id. `include=no_url` brings them back in any other selector, and `include=all` does too:

```php
$slides = lynx()->pages->findOne('template=hero-slides')?->children();   // below a page without a URL
$promos = lynx()->pages->find('template=promo, sort=title');                 // their template, named
$everything = lynx()->pages->find('has_draft=1, include=no_url');
```

## Sorting and limits

`sort` takes a field or a page property; a `-` sorts descending. Several `sort` clauses sort by each in turn. `limit` caps the number of pages, and `start` skips some, for paging through results:

```php
lynx()->pages->find('template=post, sort=-published, sort=title, start=20, limit=10');
```

Without `sort`, pages come in the order editors gave them among their siblings (`sort=sort`), then by id. Besides fields, you can sort by `name`, `id`, `created`, `modified` and `published`.

### Selectors from visitors

Some apps take a whole selector from a request, such as an API that lets its clients filter pages. Give it a cap, so a client can't ask for every page at once:

```php
lynx()->pages->find($request->string('q'), maxLimit: 50, defaultLimit: 20);
```

The usual case is different: your selector, with a visitor's words in it. Never paste those words in as they come, as in `"template=post, title%={$request->q}"`: a visitor could add conditions of their own, such as `include=unpublished` or `status=unpublished` to list unpublished pages. Check them against what you expect (a known category name, digits for an id) before they go in. For free text, remove double quotes and put the text inside double quotes: inside them, commas, pipes and single quotes are plain text. [Visitor Input](/basics/visitor-input#words-in-a-selector) shows the whole step.

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

To try a selector, run it in Tinker (`php artisan tinker`) and look at what comes back. `lynx:find` runs one straight from the command line, and `--sql` prints the query it compiles to:

```bash
php artisan lynx:find "template=course, sort=title" --lang=pt --sql
```
