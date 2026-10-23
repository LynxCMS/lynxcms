---
title: Selectors
description: Find pages with ProcessWire style selectors, in any language.
order: 4
---

# Selectors

```php
lynx()->pages->find('template=course, parent=/courses/, title%=risk, price<300, sort=-title, limit=10');
lynx()->pages->count('template=course');
```

## Operators

| Operator | Matches |
| --- | --- |
| `=` and `!=` | Equal, not equal. |
| `<`, `>`, `<=`, `>=` | Comparisons. |
| `%=` and `*=` | Contains. |
| `~=` | Contains every word, in any order: `summary~="risk management"` finds "management of risk". |
| `^=` | Starts with. |
| `$=` | Ends with. |

The text operators (`%=`, `*=`, `~=`, `^=`, `$=`) ignore case. `|` separates alternative values: `template=course|lesson`.

With `!=` the page must differ from every value: `status!=draft|archived` finds neither. An empty value means empty or missing: `summary=` finds pages without a summary, `price!=` pages with a price.

## Several fields at once

`|` between field names matches when any of them matches:

```php
lynx()->pages->find('template=course, title|summary|body%=risk');
```

With `!=` it matches when none of them holds the value. `sort`, `limit`, `start`, `include`, `language` and `search` cannot be combined with other names.

## Counting

`.count` compares how many child pages, references or items a page has:

```php
lynx()->pages->find('template=category, children.count>0');   // categories with pages
lynx()->pages->find('template=course, related.count>=2');
lynx()->pages->find('template=project, gallery.count=0');      // projects without images
```

`children.count` leaves drafts and system pages out and counts hidden and unpublished children. Fields that hold several values (page references, choices with several values, repeaters, matrices, images and files) can be counted; for items, only the switched on ones count.

Date fields compare with dates (`starts_on>=2026-01-01`) and with words PHP understands as dates: `starts_on>=today`, `starts_on<-7 days`. A value with no time means midnight.

## Inside repeaters

Repeater subfields work with a dot: a page matches when one of its items (switched on) matches.

```php
lynx()->pages->find('template=project, credits.partner=/parceiros/rtp/');
lynx()->pages->find('credits.role%=realiz, language=pt');
lynx()->pages->find('blocks.type=video');   // matrix item types
```

`!=` finds pages where no item matches. Lists such as page references take `=` and `!=` only. Sorting by a subfield is not supported.

## Inside images and files

Images and files fields work the same way, one item per file. Hidden files do not count.

```php
lynx()->pages->find('gallery.tags=hero');
lynx()->pages->find('hero.width>=1600');
lynx()->pages->find('downloads.mime=application/pdf');
lynx()->pages->find('gallery.description%=rodagem, language=pt');
```

Files offer `file`, `mime`, `bytes`, `tags` and `description`; images add `width` and `height`. Inside repeater and matrix items, add the subfield's name: `blocks.gallery.count>0`, `blocks.gallery.tags=hero`.

## Searching text

`search*=` looks for words in the search index: the title of every page, plus the fields each template marks searchable (see [Search](/basics/templates-and-fields#search)), matrix and repeater items included.

```php
lynx()->pages->find('search*="quatro estações", limit=20');
lynx()->pages->find('search*="seasons", template=project, language=en');
```

Every word must appear, as the beginning of a word: `estaç` finds "estações", `ções` does not. Case and accents are ignored, so `estacoes` finds it too. `|` separates alternatives. The search runs in the language being read, with fallback, on SQLite (FTS5) and PostgreSQL (full text with a GIN index). Other databases use `LIKE`. `%=` and `~=` are accepted as synonyms.

## Languages

Selectors match the values visitors see in the current language, fallback included. Add `language=pt` to match another language.

## Status

Hidden and unpublished pages are left out. `include=hidden`, `include=unpublished` or `include=all` brings them back.

## Sorting and limits

`sort=title`, `sort=-price` (descending), `start=20`, `limit=10`.

Selectors that come from visitors (a search box, a query string) need a cap:

```php
lynx()->pages->find($request->string('q'), maxLimit: 50, defaultLimit: 20);
```

## Which fields load

Found pages come with their names, status and title. Every other field loads the first time a template reads it, in one query for all the pages found together, so listing 50 courses and showing their summary costs one query for the summaries, not 50. Fields a list never shows are never loaded.

When you know a list shows more fields, `join=` loads them with the pages:

```php
lynx()->pages->find('template=course, join=summary|price');
lynx()->pages->find('template=course, join=all');   // every field, as before
```

A field that nearly every list shows can load with every page instead: turn on **Load with the page** in the field's settings (`"autoload": true` through the API). This is ProcessWire's autojoin; unlike ProcessWire, a field that is not joined still loads once per list, never once per page.

## Large sites

Sorting by a translatable field compares the value each language shows, fallback included. For the fields named in `lynx.sort_keys` (the title by default) Lynx stores that value per page and language, so `sort=title` reads an index. Keys follow every save and language change. After adding a field to `sort_keys`, or changing a field's fallback setting, run:

```bash
php artisan lynx:sort-keys
```

Until then, sorting by the new field works the slow way.

On PostgreSQL, trigram indexes speed up `%=`, `*=`, `~=`, `^=` and `$=` on text fields:

```bash
php artisan lynx:trigram    # installs pg_trgm and indexes the text fields
```

Set `LYNX_TRIGRAM=true` so new fields get the index and translatable fields use it. Rare words become much faster to find (90 ms to 6 ms on 50,000 pages); words that match thousands of pages get a little slower (3 ms to 26 ms), and saving text costs a little more.

## Debugging

```bash
php artisan lynx:find "template=course, sort=title" --lang=pt --sql
```

`--sql` prints the compiled query.
