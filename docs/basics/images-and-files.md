---
title: Images and files
description: Images and documents that belong to a page, with descriptions per language, tags, focus points and sizes made on demand.
order: 3
---

# Images and files

`lynxcms/media` adds two field types. Run `php artisan storage:link` once so the `public` disk is served.

```php
lynx()->fields->add('gallery', 'images', settings: ['max_files' => 12, 'tags' => ['hero', 'detail']]);
lynx()->fields->add('poster', 'images', settings: ['max_files' => 1, 'min_width' => 800]);
lynx()->fields->add('downloads', 'files', settings: ['extensions' => 'pdf zip']);
```

| Setting | Meaning |
| --- | --- |
| `max_files` | Most files; `1` makes templates read one file instead of a list. |
| `extensions` | Allowed extensions, space separated. |
| `max_kb` | Largest upload. |
| `min_width`, `min_height` | Smaller images are refused. |
| `max_width`, `max_height` | Larger images are scaled down on upload (default 2560). |
| `tags` | Tags editors may choose; empty allows any. |

Files live in `storage/app/public/lynx/{page id}/` (`LYNX_MEDIA_DISK` picks another disk). In the admin, drop files on the field, then drag them into order (or use the arrow buttons under each file), hide, describe them per language, tag them and click the focus point.

Files are stored with their page, so a new page cannot hold them yet. A required images or files field does not block creating a page: the New page screen leaves it out and creates the page unpublished. Add the files in the editor, then publish. Every save after creation needs the field filled.

## In templates

```blade
@foreach ($page->gallery as $image)
    <x-lynx-image :image="$image" :width="800" :height="600" />
@endforeach

<x-lynx-image :image="$page->poster" :width="1200" sizes="100vw" />
<a href="{{ $page->downloads->first()?->url() }}">{{ $page->downloads->first()?->description() }}</a>
```

| Method | Gives |
| --- | --- |
| `url()`, `width()`, `height()` | The original. |
| `size(800, 600)` | Exactly that size, cropped around the focus point. |
| `resize(400)` | 400 pixels wide, keeping the ratio, never larger than the original. |
| `webp()`, `preset('card')` | A WebP copy; a named size from `config/lynx-media.php`. |
| `srcset([400, 800])` | A srcset value. |
| `description()`, `tags()`, `hasTag('hero')` | In the current language, with fallback. |

`$page->gallery->tagged('hero')` filters by tag. Sizes are made the first time their file or URL is asked for, always from the original, and kept next to it. Calls chain into one file: `$image->size(300, 300, 75)->webp()` is a single WebP named `photo.300x300-q75.webp`.

`withTags()` keeps the files that have every tag given, and returns a list you can keep filtering. `inLanguage()` keeps the files tagged with the current language's code; when there are none, it tries the next language along the fallback chain, then the files tagged with no language. Tag files with language codes (`en`, `pt`) to offer one download per language:

```blade
@php($sheet = $page->downloads->withTags('a4', 'color')->inLanguage()->first())
@if ($sheet)
    <a href="{{ $sheet->url() }}" download>{{ $sheet->description() ?: $sheet->name() }}</a>
@endif
```

Content packages carry the files with their pages (see [Schema and content](/deeper/schema-and-content)).

Selectors look inside files too: `gallery.tags=hero`, `hero.width>=1600`, `blocks.gallery.count>0`. See [Selectors](/basics/selectors).

## From code and agents

```php
use LynxCms\Media\Actions\StoreMedia;

$entry = lynx()->run(StoreMedia::class, ['page' => '/projects/doc/', 'field' => 'gallery', 'url' => 'https://example.com/still.jpg'])->value;

$page = lynx()->pages->get('/projects/doc/');
$page->gallery = [...$page->getUnformatted('gallery'), ['values' => ['description' => ['pt' => 'Rodagem']] + $entry]];
$page->save();
```

`media.store` is also an MCP and agent tool (URL or base64). Files no page, draft or revision uses are deleted by `php artisan lynx:media:prune`, which the scheduler runs daily.
