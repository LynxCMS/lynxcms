---
name: lynx-templates
description: Build Lynx page templates and front-end views. Use when creating or changing Blade views in resources/views/templates, printing page fields, linking between languages, listing pages with selectors, or embedding Livewire components editors choose.
---

# Lynx page templates

A page on template `course` renders `resources/views/templates/course.blade.php` with `$page` (and `$lynx`). Missing views fall back to `lynx::page`.

## Printing values

```blade
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>@hreflang($page)</head>
<body>
    <h1>@field($page->title)</h1>   {{-- marks fallback values with lang="..." --}}
    @field($page->body)              {{-- html fields are already cleaned and formatted --}}
    <p>{{ $page->price }} USD</p>
    @lynxComponent($page, 'enrol')   {{-- a Livewire component chosen by editors --}}
</body>
</html>
```

- `$page->title` returns the value in the current language, falling back along the language chain; `$page->title->isFallback()` tells which.
- `$page->getUnformatted('body')` gives the stored value without formatters.
- `$page->url` is the page's URL in the current language; `$page->localUrl('pt')` in another one.
- Settings pages: `lynx()->settings()->footer_text`.

## Matrix blocks

`@matrix($page->blocks)` renders each block with `templates/fields/{field}/{type}.blade.php` (a `{template}/{type}` view first), passing `$block`, `$page` and `$loop`. `$block->type()` is the type's slug.

## Editable texts

`@string('hero.title', 'Default text', label: 'Hero title')` prints a text editors can change per language (Texts tab); `scope: 'site'` shares it across pages, `format: 'markdown'` renders Markdown. Keys and texts must be literals.

## Language switcher

Use the switcher helper: it lists only languages the page is published in and keeps the query string (filters, a draft preview). `@hreflang` stays without query strings for search engines.

```blade
@foreach (lynx()->languages->switcher($page) as $link)
    <a href="{{ $link['url'] }}" @if ($link['isCurrent']) aria-current="page" @endif>{{ $link['language']->name }}</a>
@endforeach
```

## Lists

```blade
@foreach (lynx()->pages->find('template=course, parent='.$page->id.', sort=title') as $course)
    <a href="{{ $course->url }}">@field($course->title)</a>
@endforeach
```

- Selectors match the values visitors see in the current language; add `language=pt` to choose another.
- Hidden and unpublished pages are left out unless the selector says `include=all`.
- Selectors from a search box or query string need a cap: `lynx()->pages->find($q, maxLimit: 50, defaultLimit: 20)`.

## Drafts

Editors preview drafts at the page's URL with `?preview=draft`; nothing in the template needs to change for it.
