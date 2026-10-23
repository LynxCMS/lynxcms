---
title: Templates and views
description: Render pages with Blade, print fields, switch languages and list pages.
order: 1
---

# Templates and views

Lynx serves pages through one catch-all route. The URL prefix picks the language and the path picks the page. A page on template `course` renders `resources/views/templates/course.blade.php` with `$page`. Without that view, Lynx uses its own `lynx::page`.

```blade
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>@hreflang($page)</head>
<body>
    <h1>@field($page->title)</h1>   {{-- adds lang="en" when the value is a fallback --}}
    @field($page->body)              {{-- html fields are already cleaned and formatted --}}
    <p>{{ $page->price }} USD</p>
    @lynxComponent($page, 'enrol')   {{-- a Livewire component editors choose --}}
    <footer>@field(lynx()->settings()->footer_text)</footer>
</body>
</html>
```

| Helper | Does |
| --- | --- |
| `@field($value)` | Prints a value and marks a fallback with the `lang` of its language. |
| `@hreflang($page)` | Alternate links for every language the page is published in. |
| `@lynxComponent($page, 'field')` | Renders the component chosen in a `component` field. |
| `$page->url` | The URL in the current language. |
| `$page->localUrl('pt')` | The URL in another language. |

## Reading values

Values are made to test directly in templates:

- A text with no value reads as `null`, so `@if ($page->subtitle)`, `$page->subtitle ?: 'None'` and `??` work. A filled text is a value object that prints formatted; cast it, `(string) $page->subtitle`, to compare it with a string. A translatable text reads as `null` only when no language has a value.
- A page reference that holds one page (`multiple: false`) reads as that page or `null`: `{{ $page->category?->title }}`. One that holds several reads as a `PageArray`.
- The same goes for the fields of repeater and matrix items.

A `PageArray` joins and filters its pages itself:

```blade
{{ $publication->topics->implode(', ', 'title') }}
{{ $pages->filter(fn ($page) => $page->featured)->implode(' · ', fn ($page) => $page->name()) }}
```

Use these rather than `collect($pages)->map(...)->implode(', ')`: Laravel's `implode()` treats field values as arrays to pluck from and returns empty strings.

A field the page's template does not have reads as `null`, so `$page->cover?->url()` and `$page->subtitle ?? ''` are safe in partials shared by several templates. In debug mode (`APP_DEBUG`), reading a field that does not exist in the schema at all, usually a typo such as `$page->titel`, writes a warning to the log, once per template and request.
| `lynx()->settings()` | The settings page (a singleton template without URL). |

## Language switcher

```blade
@foreach (lynx()->languages->switcher($page) as $link)
    <a href="{{ $link['url'] }}" @if ($link['isCurrent']) aria-current="page" @endif>{{ $link['language']->name }}</a>
@endforeach
```

The switcher lists only the languages the page is published in and keeps the query string (filters, sorting, a draft preview). `@hreflang` leaves query strings out, so search engines see one URL per language.

## Lists

```blade
@foreach (lynx()->pages->find('template=course, parent='.$page->id.', sort=title') as $course)
    <a href="{{ $course->url }}">@field($course->title)</a>
@endforeach
```

See [Selectors](/basics/selectors).

## Matrix blocks

```blade
@matrix($page->blocks)
```

Each block renders with the view of its type: `resources/views/templates/fields/blocks/course/video.blade.php` for pages on the `course` template, else `templates/fields/blocks/video.blade.php`. The view gets `$block`, `$page` and `$loop`:

```blade
{{-- templates/fields/blocks/quote.blade.php --}}
<blockquote>@field($block->quote) <cite>{{ $block->cite }}</cite></blockquote>
```

With `APP_DEBUG` on, a block without a view leaves an HTML comment naming the view to create. Loop over `$page->blocks` yourself for full control; `$block->type()` gives the slug.

## Editable texts

Short texts that belong to the design, such as a headline or a button label, can be declared in the template and changed by editors without a field:

```blade
<h1>@string('hero.title', ['pt' => 'Produções audiovisuais', 'en' => 'Audiovisual productions'], label: 'Hero title')</h1>
<a href="/contacto">@string('cta.contact', 'Fale connosco')</a>
<p>@string('hero.intro', 'Contamos histórias com **imagens**.', format: 'markdown')</p>
<footer>@string('footer.note', '© Esfera Cúbica', scope: 'site')</footer>
```

The text in the template is the default; editors change it per language in the page's **Texts** tab, or in **Site texts** for `scope: 'site'` strings, which every page shares. Strings in views under a `layouts` folder are site strings unless they say `scope: 'page'`, so a layout's footer note is one text for the whole site (`lynx.strings.site_folders` names other folders). Only their changes are stored, so a new default in the template reaches every page nobody edited. Keys and texts must be literals. In a map, the keys are your languages' codes: a text is picked for the current language, then its fallbacks, then the default language, and otherwise the first text given. A key no language uses (`en` where English is `default`) is never picked by language. `php artisan lynx:strings:scan` lists every string and reports such keys and other problems.

A page's own texts are part of the page: while it has a draft, the Texts tab edits the draft, and revisions keep and restore them. Site texts are shared by every page, so they are always live.

## Old URLs

Renamed and moved pages redirect from their old paths, in every language. Editors can remove an old path from the page's settings.

## Drafts

Editors preview a page's draft at its URL with `?preview=draft`. Templates need no changes for it.

## Turning routing off

Set `LYNX_ROUTES=false` to serve pages from your own routes, using `lynx()->pages->get()`.
