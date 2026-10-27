---
draft: true
title: Listing and Linking Pages
description: Chapter 5 of the bilingual website guide. Lists of pages with selectors, the team grouped by role, page references read from both sides, photos, and dates in the visitor's language.
---

# Listing and Linking Pages

Most of a website's pages show other pages: the news page lists the news, a lawyer's page links to their practice areas, the home page shows the latest cases. This chapter writes those lists. They all use **selectors**, short texts that say which pages you want, in what order and how many.

## The news, newest first

The news page lists the pages below it. `$page->children()` returns them, and a selector sorts them:

```blade
{{-- resources/views/templates/news.blade.php --}}
<x-layout :page="$page">
    <x-page-header :title="$page->title" :summary="$page->summary" />
    @foreach ($page->children('sort=-published_on') as $post)
        <x-post-row :post="$post" />
    @endforeach
</x-layout>
```

`sort=-published_on` sorts by the date field, and the `-` puts the newest first. Lists leave out pages visitors may not see, such as a news item that is not published yet.

Each post shows its date in the visitor's language. A date field reads as a [Carbon](https://carbon.nesbot.com) date object, which formats itself for a locale:

```blade
{{-- resources/views/components/post-row.blade.php, shortened --}}
<time datetime="{{ $post->published_on?->toDateString() }}">{{ $post->published_on?->locale(app()->getLocale())->isoFormat('LL') }}</time>
<span>{{ $post->category }}</span>
<h3><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
```

`isoFormat('LL')` gives *March 12, 2026* on the English site and *12 mars 2026* on the French one. `$post->category` prints the choice's label in the visitor's language, *Case* or *Dossier*. The `?->` skips the date if a post has none.

## The latest cases on the home page

To find pages anywhere in the tree, not only below one page, use `lynx()->pages->find()`:

```php
$latest = lynx()->pages->find('template=post, sort=-published_on, limit=3');
$founders = lynx()->pages->find('template=lawyer, role=founding-partner');
```

Each part between commas is a condition, and a page must match all of them. The first line reads "posts, newest first, at most three". The second finds the lawyers whose role is `founding-partner`: a choice compares by its value, never its label, so the selector is the same in both languages. [Finding Pages](/basics/selectors) lists every condition a selector can have.

## The team, grouped by role

The team page shows founding partners first, then partners, associates and paralegals. That is the order of the role field's options, so the view loops over the options and lists the lawyers of each:

```blade
{{-- resources/views/templates/team.blade.php, shortened --}}
@foreach (lynx()->fields->choices('role') as $role)
    @php($people = $page->children("role={$role->value}"))
    @if ($people->count())
        <section>
            <h2>{{ $role->label() }}</h2>
            @foreach ($people as $lawyer)
                <a href="{{ $lawyer->url() }}">
                    <x-portrait :page="$lawyer" />
                    <h3>{{ $lawyer->title }}</h3>
                    <p>{{ $lawyer->species }} · {{ $lawyer->office?->city }}</p>
                </a>
            @endforeach
        </section>
    @endif
@endforeach
```

`lynx()->fields->choices('role')` returns the field's options in order, each with its `value` and its label in the visitor's language. If the firm adds a role in the admin, the team page shows it without a change to the view. The starter's view writes each heading as an editable text instead of the label, so the firm can say *Associés fondateurs* in a heading and *Associé fondateur* on a lawyer's page.

`$lawyer->office` is the office page the lawyer points at, so `$lawyer->office?->city` prints its city. `?->` prints nothing for a lawyer without an office.

## One field, read from both sides

A practice area names its lawyers in its `lawyers` field. The practice area's page reads them directly:

```blade
{{-- resources/views/templates/practice-area.blade.php, shortened --}}
@foreach ($page->lawyers as $lawyer)
    <x-lawyer-link :lawyer="$lawyer" />
@endforeach
```

A lawyer's page shows the practice areas the lawyer works in, with no field of its own. It looks for the practice areas whose `lawyers` field holds this lawyer:

```blade
{{-- resources/views/templates/lawyer.blade.php, shortened --}}
@php($areas = lynx()->pages->find("template=practice-area, lawyers={$page->id}"))
@foreach ($areas as $area)
    <a href="{{ $area->url() }}">{{ $area->title }}</a>
@endforeach
```

The link is stored once, on the practice area, so the two pages can never disagree: when an editor adds a lawyer to an area, the lawyer's page lists that area at once. The office page finds its people the same way, with `template=lawyer, office={$page->id}`, and a practice area finds its latest cases with `template=post, practice_areas={$page->id}`.

## Photos

A lawyer's photo is an `images` field holding one image. `<x-lynx-image>` prints it at the size the page needs, cropped around the point an editor marked in the admin:

```blade
{{-- resources/views/components/portrait.blade.php, shortened --}}
@if ($page->photo)
    <x-lynx-image :image="$page->photo" :width="480" :height="384" />
@else
    <x-illustration :name="$page->illustration?->value" />
@endif
```

Lynx makes each size the first time a page asks for it, and keeps it for the next visitor. The image's description, which editors write in each language, becomes its `alt` text. The starter's lawyers have drawings instead of photos, so the component falls back to a drawing chosen by the page's `illustration` field. [Images and Files](/basics/images-and-files) covers sizes, focus points and uploads.

## A title that agrees with the person

Chapter 2 gave lawyers a `job_title` field for French titles that agree with the person. A small component prints it when it has a value in the visitor's language, and the role otherwise:

```blade
{{-- resources/views/components/job-title.blade.php --}}
@props(['lawyer'])
{{ $lawyer->job_title ?: $lawyer->role }}
```

An empty text field reads as `null`, so `?:` moves on to the role. Olive Otter has a French job title, *Associée fondatrice*; Castor has none, so French visitors read his role, *Associé fondateur*.

## What you have now

- **The firm:** a team page, a news page, practice areas that link to their lawyers and lawyers that link back, all in the visitor's language, and all updating as editors add pages.
- **You:** lists written as one line each, with no SQL and no translation code in the views.
