---
title: Images and Files
description: Image and file fields, uploads, responsive sizes made on demand, focus points, tags and downloads per language.
---

# Images and Files

The `lynxcms/media` package adds two field types: `images`, for photos and illustrations, and `files`, for documents such as PDFs. Files belong to the page that holds them, each with a description per language and tags. Images also have a focus point, and Lynx makes the sizes your templates ask for on demand, cropped around that point.

## Installation

```bash
composer require lynxcms/media
php artisan storage:link
```

`storage:link` makes the `public` disk reachable from the web, as in any Laravel app. Files are stored in `storage/app/public/lynx/{page id}/`; set `LYNX_MEDIA_DISK` to use another disk.

## Adding fields

```php
lynx()->fields->add('gallery', 'images', settings: ['max_files' => 12, 'tags' => ['hero', 'detail']]);
lynx()->fields->add('cover', 'images', settings: ['max_files' => 1, 'min_width' => 800]);
lynx()->fields->add('downloads', 'files', settings: ['extensions' => 'pdf zip']);
```

| Setting | Meaning |
| --- | --- |
| `max_files` | Most files. `1` makes templates read one file instead of a list. |
| `extensions` | Allowed extensions, separated by spaces. |
| `max_kb` | Largest upload, in kilobytes. |
| `min_width`, `min_height` | Smaller images are refused. |
| `max_width`, `max_height` | Larger images are scaled down on upload. Default 2560. |
| `tags` | Tags editors may choose; empty allows any tag. |
| `private` | Keep the files private: see [Private files](#private-files). |

[Field Types](/basics/field-types#media) lists the defaults.

## Uploading in the admin

Editors drop files on the field, then drag them into order (or use the arrow buttons under each file). Each file can be hidden, described in each language and tagged, and each image gets a focus point with a click.

Files are stored with their page, so a page must exist before it can hold them. A required images or files field doesn't block creating a page: the New page screen leaves it out and creates the page unpublished. Editors add the files in the page editor, then publish it.

## Showing images

The `<x-lynx-image>` component prints an `<img>` tag with the size you ask for, its `width` and `height`, a `srcset` for sharp images on every screen, and lazy loading:

```blade
<x-lynx-image :image="$page->cover" :width="1200" sizes="100vw" />

@foreach ($page->gallery as $image)
    <x-lynx-image :image="$image" :width="400" :height="300" />
@endforeach
```

With a width and a height, the image is cropped to exactly that size around its focus point. With only a width, it keeps its proportions. The image's description in the current language becomes the `alt` text, unless you pass `alt`; pass `:lazy="false"` for an image at the top of the page.

For other markup, an image gives you its sizes directly:

| Method | Gives |
| --- | --- |
| `url()`, `width()`, `height()` | The original. |
| `size(800, 600)` | Exactly that size, cropped around the focus point. |
| `resize(400)` | 400 pixels wide, keeping the proportions, never larger than the original. |
| `webp()` | A WebP copy. |
| `preset('card')` | A named size from `config/lynx-media.php` (`thumb`, `card` and `hero` by default). |
| `srcset([400, 800])` | A `srcset` value. |
| `description()`, `tags()`, `hasTag('hero')` | Its description and tags, in the current language with fallback. |

```blade
<div style="background-image: url('{{ $page->cover->size(1920, 600)->webp()->url() }}')"></div>
```

Sizes are made the first time their file or URL is asked for, always from the original, and kept next to it. Calls chain into one file: `$image->size(300, 300, 75)->webp()` is a single WebP file, `photo.300x300-q75.webp`.

## Tags

Tags group the files of a field. `tagged()` keeps the files with a tag:

```blade
@foreach ($page->gallery->tagged('hero') as $image)
    <x-lynx-image :image="$image" :width="1600" />
@endforeach
```

## Downloads per language

For documents in several languages, tag each file with its language's code (`en`, `pt`). `inLanguage()` keeps the files of the current language; when there are none, it tries the next language of the fallback chain, then the files tagged with no language. `withTags()` keeps the files that have every tag given:

```blade
@php($sheet = $page->downloads->withTags('a4', 'color')->inLanguage()->first())

@if ($sheet)
    <a href="{{ $sheet->url() }}" download>{{ $sheet->description() ?: $sheet->name() }}</a>
@endif
```

## Finding pages by their files

Selectors look inside images and files, one item per file:

```php
lynx()->pages->find('gallery.tags=hero');
lynx()->pages->find('cover.width>=1600');
lynx()->pages->find('template=project, gallery.count=0'); // projects without images
```

See [Finding Pages](/basics/selectors#inside-repeaters-blocks-and-files) for every property.

## Storing files from code

To attach a file from code, such as an image an importer downloads, store it with the `media.store` action, then add the returned entry to the field:

```php
use LynxCms\Media\Actions\StoreMedia;

$entry = lynx()->run(StoreMedia::class, [
    'page' => '/projects/documentary',
    'field' => 'gallery',
    'url' => 'https://example.com/still.jpg',
])->value;

$page = lynx()->pages->get('/projects/documentary');
$page->gallery = [...$page->getUnformatted('gallery'), ['values' => ['description' => ['en' => 'On set']] + $entry]];
$page->save();
```

`media.store` is also a tool of the MCP server and the AI agent, which can pass a URL or the file's contents.

## Private files

Files on the public disk have public addresses: anyone with a link can download them, even when the page they belong to is behind a password or open only to members. For files that must follow the page, such as paid course materials, turn on the field's **Private** setting:

```php
lynx()->fields->add('handouts', 'files', settings: ['private' => true]);
```

A private field's files are stored on the private disk (`LYNX_MEDIA_PRIVATE_DISK`, Laravel's `local` disk by default, which the web server never serves), and their URLs point to Lynx: `/lynx-media/{page id}/{file}`. Lynx serves a file only to someone who may open its page:

- staff whose roles let them view the page, in the admin or on the site;
- visitors, when the page is published, no page gate answers instead and the [visibility rules](/deeper/visibility) allow it.

A gate's answer is the file's answer, so a page behind a password shows its password form, and a members-only page sends guests to sign in. Responses are marked `private` and `noindex`, so shared caches and search engines keep no copy. Templates need no change: `$file->url()` gives the right address, and image sizes of private images are made and served the same way.

Turning the setting on or off moves the field's existing files to the other disk, inside the same change: the originals and their image sizes are deleted from the old disk once it is saved, and sizes are made again when next asked for. Files of private fields travel in content packages like any other, and stay private on import.

Private files cost a PHP request each, so keep public images (logos, photos on public pages) in public fields. They also don't work in a [static site export](/modules/static-site), whose pages have no Lynx to ask.

## Cleaning up

Files that no page, draft or revision uses, on the public and the private disk, are deleted by `php artisan lynx:media:prune`, which the scheduler runs daily, once they are a day old. [Content packages](/deeper/schema-and-content) carry the files of the pages they hold.
