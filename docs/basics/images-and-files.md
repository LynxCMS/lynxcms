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

Create the fields once, in `php artisan tinker` or a seeder (a Laravel class that fills the database, run with `php artisan db:seed`):

```php
lynx()->fields->create('gallery', 'images', settings: ['max_files' => 12, 'tags' => ['hero', 'detail']]);
lynx()->fields->create('cover', 'images', settings: ['max_files' => 1, 'min_width' => 800]);
lynx()->fields->create('downloads', 'files', settings: ['extensions' => 'pdf zip']);
```

| Setting | Meaning |
| --- | --- |
| `max_files` | Most files. `1` makes templates read one file instead of a list. |
| `extensions` | Allowed extensions, separated by spaces. Types a web server or a browser runs (`php`, `phtml`, `phar`, `html`, `svg`, `js`, `xml` and the like) are never allowed, since media files are served from your site's address. |
| `max_kb` | Largest upload, in kilobytes. |
| `min_width`, `min_height` | Smaller images are refused. |
| `max_width`, `max_height` | Larger images are scaled down on upload. Default 2560. |
| `tags` | Tags editors may choose; empty allows any tag. |
| `private` | Keep the files private: see [Private files](#private-files). |

[Field Types](/basics/field-types#media) lists the defaults.

## Uploading in the admin

Editors drop files on the field, then drag them into order (or use the arrow buttons under each file). Each file can be hidden, described in each language and tagged, and each image gets a focus point with a click.

A photo larger than the field's `max_width` and `max_height` is scaled down in the browser before it uploads, so a 20 MB photo from a phone arrives as a file of a few hundred kilobytes and fits the upload limit. JPEG, PNG and WebP files are scaled; animated images and other types are sent as they are. The server checks every file and scales it again if needed, as files stored through `media.store`, MCP clients or the agent don't pass through the browser.

Files are stored with their page, so a page must exist before it can hold them. A required images or files field doesn't block creating a page: the New page screen leaves it out and creates the page unpublished. Editors add the files in the page editor, then publish it.

## Showing images

The `<x-lynx-image>` component prints an `<img>` tag with the size you ask for, its `width` and `height`, a `srcset` for sharp images on every screen, and lazy loading. Use it in the template's view, such as `resources/views/templates/post.blade.php`:

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

### Sizes in a queue

A page that shows many new sizes at once, such as a gallery right after an upload, makes all of them while it renders, so its first visitor waits. With a [queue worker](https://laravel.com/docs/queues#running-the-queue-worker) running, you can have a queue make them instead. Set it in `.env`:

```ini
LYNX_MEDIA_QUEUE_SIZES=true
```

A size that doesn't exist yet is then made by a queued job, and the page shows the nearest image that exists in the meantime: the smallest larger size of the same picture, or the original. `<x-lynx-image>` with both a width and a height crops that image in the browser at the focus point, so it looks the same, only heavier to load. If you print `$image->size(...)->url()` into an `<img>` of your own, give it `object-fit: cover` for the same result. The page cache doesn't store a page while it shows such an image, and stores it once the sizes are made.

Code that reads a size's file, through `$image->size(...)->file()`, still gets it made at once, and a static site build makes every size while it renders. The setting is off by default. `php artisan lynx:doctor` warns when it is on and the queue connection is `sync`, as each size would then be made during the render all the same.

## Tags

Tags group the files of a field. `tagged()` keeps the files with a tag, here in a template's view:

```blade
@foreach ($page->gallery->tagged('hero') as $image)
    <x-lynx-image :image="$image" :width="1600" />
@endforeach
```

## A file per language

You can give a page a different image or document in each language, such as a banner with text in it, or a brochure per language. Turn on **A file per language** in the field's settings, or `per_language` in code, once in `php artisan tinker` or a seeder:

```php
lynx()->fields->create('hero', 'images', settings: ['max_files' => 1, 'per_language' => true]);
```

The field then shows a tab per language in the page editor. Each tab has two groups, each with its own upload zone: the language's own files, and the files **Shared by every language**, which every tab shows. A file's details have a **Language** select to move it to another language or to the shared files. A tab without files of its own says what visitors see instead.

Templates read the field as before, and get the current language's files. In a template's view, such as `resources/views/templates/home.blade.php`:

```blade
<x-lynx-image :image="$page->hero" :width="1200" sizes="100vw" />
```

A language sees its own files and the files for every language, its own first, so `$page->hero` with `max_files: 1` is the Portuguese banner in Portuguese and the shared one elsewhere. A language with neither falls back along its [fallback chain](/basics/languages#fallback), as text does; with the field's `fallback: none`, it shows nothing. `max_files` counts per language. `$page->getUnformatted('hero')` still holds every file, each with its `language`, and selectors can test it: `hero.language=pt`. From code, [`media.store`](#storing-files-from-code) takes a `language`.

## Downloads per language

When one list mixes files for several languages and other tags, such as sheets in A4 and Letter, tag each file with its language's code (`en`, `pt`). `inLanguage()` keeps the files of the current language; when there are none, it tries the next language of the fallback chain, then the files tagged with no language. `withTags()` keeps the files that have every tag given. In a template's view:

```blade
@php($sheet = $page->downloads->withTags('a4', 'color')->inLanguage()->first())

@if ($sheet)
    <a href="{{ $sheet->url() }}" download>{{ $sheet->description() ?: $sheet->name() }}</a>
@endif
```

## Finding pages by their files

Selectors look inside images and files, one item per file. These calls work anywhere in your app: a view, a controller, a job:

```php
lynx()->pages->find('gallery.tags=hero');
lynx()->pages->find('cover.width>=1600');
lynx()->pages->find('template=project, gallery.count=0'); // projects without images
```

See [Finding Pages](/basics/selectors#inside-repeaters-blocks-and-files) for every property.

## Storing files from code

To attach a file from code, such as an image an importer downloads, store it with the `media.store` action, then add the returned entry to the field. The code runs wherever your import does, such as an Artisan command (a `php artisan` command of your app), a queued job or Tinker:

<<< @/snippets/media/store-from-code.php#store{php}

The entry goes in the new file's `values`, next to its description. A field with no files yet reads as `null`, so the example starts from `[]` then.

`media.store` is also a tool of the MCP server and the AI agent, which can pass a URL or the file's contents.

An images field stores JPEG, PNG, GIF, WebP, and AVIF files, recognized from their contents before anything decodes them, so a file named `photo.png` that holds something else is refused. On a server that uses ImageMagick (`IMAGE_DRIVER=imagick`), also limit ImageMagick's coders in its `policy.xml` to the raster formats you use, as its project recommends.

When an editor, an MCP client or the agent saves a page, each file its media fields name must be stored in that page's folder (a draft may name the files of the page it belongs to), with an extension the field allows. An images field takes only pictures. The file's size, type and dimensions are read from the file itself, whatever the value says. Superusers and your own code (Artisan commands, jobs, seeders) can save any value, so an import can keep the names another site gave its files.

URLs are downloaded only from public web servers: `http` and `https` on their usual ports, from hosts whose addresses are on the internet, never the server's own network or a cloud's metadata address. Redirects get the same checks, and the download stops once it passes the field's size limit. To download from a host of your own network, such as an asset server, add it to `downloads.allowed_hosts` in `config/lynx-media.php`. `LYNX_MEDIA_URL_DOWNLOADS=false` turns URL downloads off.

## Private files

Files on the public disk have public addresses: anyone with a link can download them, even when the page they belong to is behind a password or open only to members. This holds from the moment a file is uploaded, whatever the page's status: the web server sends files on the public disk without asking Lynx, so an unpublished page's files are kept only by their names, which end in 16 random characters.

Fields never accept extensions a browser would run as part of the site: HTML, SVG, JavaScript, every XML type (such as `gpx`, `kml` or `rss`), server scripts, and extensions with no known type, which a web server sends without one. Zip a file of such a type to offer it for download. As a second guard, have the web server send the public media folder (`storage/app/public/lynx`, served as `/storage/lynx`) with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`. With nginx:

```nginx
location /storage/lynx/ {
    add_header X-Content-Type-Options nosniff always;
    add_header Content-Security-Policy sandbox always;
}
```
 For files that must follow the page, such as paid course materials, turn on the field's **Private** setting. In code, create the field once, like the fields above:

```php
lynx()->fields->create('handouts', 'files', settings: ['private' => true]);
```

A private field's files are stored on the private disk (`LYNX_MEDIA_PRIVATE_DISK`, Laravel's `local` disk by default, which the web server never serves), and their URLs point to Lynx: `/lynx-media/{page id}/{file}`. Lynx serves a file only to someone who may open its page:

- staff whose roles let them view the page, in the admin or on the site;
- visitors, when the page is published, no page gate answers instead, the [visibility rules](/deeper/visibility) allow it, and the page shows the file. A file an editor removed or hid, or one only a draft holds, is for staff only, and so are the sizes made from it.

A gate's answer is the file's answer, so a page behind a password shows its password form, and a members-only page sends guests to sign in. Responses are marked `private` and `noindex`, so shared caches and search engines keep no copy. Images, video, audio, PDFs and plain text open in the browser, sandboxed; other files download. Templates need no change: `$file->url()` gives the right address, and image sizes of private images are made and served the same way.

Turning the setting on or off moves the field's existing files to the other disk, inside the same change: the originals and their image sizes are deleted from the old disk once it is saved, and sizes are made again when next asked for. Files of private fields travel in content packages like any other, and stay private on import.

Private files cost a PHP request each, so keep public images (logos, photos on public pages) in public fields. They also don't work in a [static site export](/modules/static-site), whose pages have no Lynx to ask.

### Temporary links

A temporary link hands one file to whoever holds it, until it runs out, without asking the page's rules. Use it for a gated download: a visitor fills a form, and gets a link to a private report they could not open otherwise. Make the link where you answer the form, such as a controller or an event listener:

```php
$file = $page->report->inLanguage('pt')->first();

$url = $file->temporaryUrl(now()->addDay());
```

The link is signed: changing any part of it, or using it after it expires, gets a 403 answer. The file always downloads, with `Cache-Control: private, no-store` and `noindex`, so no cache or search engine keeps it. Links work for public files too. Anyone you give a link to can pass it on until it expires, so keep the time short and send a new link instead of a long one.

## Cleaning up

Files that no page, draft or revision uses, on the public and the private disk, are deleted by `php artisan lynx:media:prune`, which the scheduler runs daily, once they are a day old. [Content packages](/deeper/schema-and-content) carry the files of the pages they hold.
