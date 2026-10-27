---
draft: true
title: Views and the Layout
description: Chapter 4 of the bilingual website guide. Blade views per template, a layout around every page, menus editors change, a language switcher, and texts editors reword.
---

# Views and the Layout

This chapter gives the site its look. Each template gets a view, every page shares one layout with the menus and a language switcher, and the short texts of the design (a button label, the footer's line) become texts editors can change in both languages.

## A view per template

Lynx shows each page with the view named after its template, in `resources/views/templates`. A lawyer's page uses `resources/views/templates/lawyer.blade.php`. Views are written in **Blade**, Laravel's template language: HTML with tags such as `{{ $page->title }}` that print values. Each view receives the page as `$page`.

A plain page, such as the legal notice, is a title and a text:

```blade
{{-- resources/views/templates/page.blade.php --}}
<x-layout :page="$page">
    <x-page-header :title="$page->title" />
    <div class="copy">@field($page->body)</div>
</x-layout>
```

`<x-layout>` and `<x-page-header>` are **Blade components**: reusable pieces of view, each a file in `resources/views/components` (`layout.blade.php`, `page-header.blade.php`). The attributes you give one, such as `:page="$page"`, become its variables.

## Printing fields

`{{ $page->title }}` prints a field's value, escaped so that text can't break the page's HTML. For text visitors read, the views use `@field` instead:

```blade
<p>{{ $page->species }}</p>
<div class="copy">@field($page->body)</div>
```

`@field` does two things more. It prints a rich text field's HTML, which Lynx cleaned when the page was saved. And when a value comes from the fallback language, because the French text isn't written yet, it wraps the value in an element with `lang="en"`, so browsers and screen readers pronounce it as English.

A field with no value reads as `null`, so a view can leave out what a page doesn't have:

```blade
@if ($page->fun_fact)
    <aside>{{ $page->fun_fact }}</aside>
@endif
```

## The layout

Every page renders inside one layout: the firm's name, the main menu, a link to search, the language switcher, the page itself and the footer. Shortened, `resources/views/components/layout.blade.php` is:

```blade
@props(['page' => null, 'title' => null, 'robots' => null])
@php($home = lynx()->pages->get('/'))
<!DOCTYPE html>
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($page)
        <x-lynx-seo :page="$page" />
    @else
        <title>{{ $title }} · {{ $home?->title }}</title>
    @endif
    @lynxCsrf
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header>
        <a href="{{ $home?->url() }}">Castor <em>&amp;</em> Otters</a>
        <nav aria-label="{{ __('site.sections') }}">
            <x-lynx-menu name="main" view="menus.navbar" />
            <x-language-switcher :page="$page" />
        </nav>
    </header>

    <main id="content">{{ $slot }}</main>

    <x-footer />
</body>
</html>
```

- `@props` lists the values the component accepts, with their defaults.
- `lynx()->languages->current()` is the language of the request. Its locale goes in `lang`, so the French site says `lang="fr-CA"`.
- `<x-lynx-seo>` prints the page's title, description and the tags search engines read (chapter 7).
- `@lynxCsrf` prepares forms for the page cache (chapter 7).
- `@vite` loads the CSS and JavaScript, which Vite, Laravel's build tool, builds from `resources/css` and `resources/js`.
- `{{ $slot }}` is where the page's own content goes.

The starter styles the site with [Tailwind CSS](https://tailwindcss.com). To get the same look, copy its `resources/css/app.css`, `resources/js/app.js`, `package.json`, the drawings in `public/images`, and the components in `resources/views/components`, then run `npm install` and `npm run build`.

## Menus editors change

The main menu is a **menu** from the menus module: a list of links that editors reorder, rename and extend in the admin's **Menus** screen. The seeder creates it with the five sections:

```php
lynx()->menus()->create('main', ['en' => 'Main navigation', 'fr' => 'Navigation principale']);

foreach (['/practice-areas/', '/team/', '/news/', '/offices/', '/contact/'] as $path) {
    lynx()->menus()->saveItem(['menu' => 'main', 'kind' => 'page', 'page' => $path]);
}
```

An item that links to a page shows that page's title, in the visitor's language, and its address in that language. When an editor renames the team page, the menu follows.

`<x-lynx-menu name="main" view="menus.navbar" />` renders the menu with a view of your own, which receives `$menu`:

```blade
{{-- resources/views/menus/navbar.blade.php --}}
@foreach ($menu->items as $item)
    @if ($item->hasUrl())
        <a href="{{ $item->url }}" @if ($item->isActive) aria-current="page" @endif
           @class(['text-copper' => $item->isActive || $item->isInActiveTrail])>{{ $item->label }}</a>
    @endif
@endforeach
```

`isActive` is true for the link to the page being shown. `isInActiveTrail` is also true for a link whose items below it, in the menu, include that page: if the Team item listed its lawyers (`'children' => true` when saving the item), the Team link would be marked on every lawyer's page. The footer menu, with the legal pages, is a second menu named `footer`.

## The language switcher

The switcher links to the same page in the other language. `lynx()->languages->switcher($page)` gives one link per language the page is published in:

```blade
{{-- resources/views/components/language-switcher.blade.php --}}
@props(['page' => null])
@if ($page && lynx()->languages->isMultilingual())
    @foreach (lynx()->languages->switcher($page) as $language)
        <a href="{{ $language['url'] }}" hreflang="{{ $language['language']->appLocale() }}"
           @if ($language['isCurrent']) aria-current="true" @endif>{{ $language['language']->code }}</a>
    @endforeach
@endif
```

On `/team/castor-belanger/` it links to `/fr/equipe/castor-belanger/`, and back. `isMultilingual()` hides the switcher on a site that has one language.

## Texts editors reword

A design has short texts that belong to it rather than to any page's content: a button label, a heading over a list, the line under the logo in the footer. Writing them in the view would mean a developer for every change. Writing them as fields would add a field per label. Instead, the views declare them with `@string`:

```blade
<a href="{{ $contact->url() }}">@string('hero.consultation', ['en' => 'Request a consultation', 'fr' => 'Demander une consultation'], label: 'Hero: consultation button')</a>
```

The first argument is the text's key, the second its default text in each language, and `label:` is what editors read in the admin. The page shows the default until an editor changes it in the page's **Texts** tab. Each page then has its own copy of that text.

Texts that should be the same on every page, such as the footer's line, say `scope: 'site'`:

```blade
<p>@string('footer.tagline', ['en' => 'Wildlife law since the last ice age, give or take.', 'fr' => 'Droit de la faune depuis la dernière glaciation, à peu près.'], label: 'Footer tagline', scope: 'site')</p>
```

Editors change site texts once, under **Site texts** in the admin. [Editable texts](/basics/views#editable-template-wide-or-site-wide-texts) explains the details, including how `php artisan lynx:strings:scan` checks them.

### Words that stay in the code

A few words are not worth an editor's attention, or depend on a number: a hidden "Skip to the content" link for screen readers, or "One page mentions…" against "3 pages mention…" on the search page. Those stay in Laravel's translation files, `lang/en/site.php` and `lang/fr/site.php`, read with `__('site.skip')` or, for counts, `trans_choice()`. Lynx finds `lang/fr` for the `fr-CA` locale too.

## The page not found

An address that matches no page shows the site's **page not found**, a page `lynx:install` made at `/not-found/`, with status 404. Editors write its text like any page's. Its view is `resources/views/templates/not-found.blade.php`:

```blade
<x-layout :title="(string) $page->title" robots="noindex">
    <p>404</p>
    <h1>{{ $page->title }}</h1>
    <p>{{ $page->summary }}</p>
    <a href="{{ lynx()->pages->get('/')?->url() }}">@string('not_found.home', ['en' => 'Back to the home page', 'fr' => 'Retour à l’accueil'], label: 'Page not found: button', scope: 'site')</a>
</x-layout>
```

It passes no `:page` to the layout, so the layout prints a plain title and no language switcher.

## What you have now

- **The firm:** a site that looks finished in both languages, with menus they rearrange and texts they reword without a developer.
- **You:** one layout, a short view per template, and the design's texts declared where they are used.
