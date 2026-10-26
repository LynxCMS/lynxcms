---
title: The Front End
description: Chapter 3 of the training site guide. Views from BlatUI's Bloom template, editable texts, menus, a language switcher and a filtered course catalogue.
---

# The Front End

The site's look comes from [Bloom](https://blatui.remix-it.com/templates/bloom), a template by BlatUI: soft clay cards, pastel shapes and rounded type. Bloom is a single static page; this chapter turns each of its sections into a view that reads Lynx content, so the staff change the site without touching the design.

## Install BlatUI

[BlatUI](https://blatui.remix-it.com) is shadcn/ui for Blade, Alpine and Tailwind: you copy its components into your app and own them. Lynx's admin is built with it too.

```bash
composer require anousss007/blatui --dev
php artisan vendor:publish --tag=blatui-foundations
php artisan blatui:add badge tabs accordion rating avatar carousel input button
npm install --save-dev alpinejs @alpinejs/anchor @alpinejs/collapse @alpinejs/focus @floating-ui/dom
```

The foundations are BlatUI's theme (`resources/css/blatui.css`) and its Alpine helpers (`resources/js/blatui.js`). `blatui:add` also suggests `gehrisandro/tailwind-merge-laravel` for the `twMerge()` macro the components use; skip it, since Lynx's admin registers that macro already.

Point the app's CSS and JavaScript at them, and add Atlas's own styles, taken from Bloom:

<<< @/snippets/training/resources/css/app.css{css}

<<< @/snippets/training/resources/js/app.js{js}

`blatui.js` registers BlatUI on the Alpine that Livewire starts, or starts its own on pages without Livewire, so tabs and accordions work on every page.

## The layout

Each page view renders inside one layout component: Bloom's floating navigation, the page, and the footer. The menus come from the menus module, so staff change the navigation in the admin, and the contact details from the settings page:

<<< @/snippets/training/resources/views/components/layouts/site.blade.php{blade}

Two Lynx features carry the layout:

- **`@string`** declares a short text in the view, with a default per language. Staff change it under **Site texts** in the admin; texts in a `layouts` folder are shared by every page. A button label needs no field of its own.
- **`@hreflang($page)`** tells search engines about the page in each language, and the language switcher links to it:

<<< @/snippets/training/resources/views/partials/language-switcher.blade.php{blade}

The main menu is rendered by a view of your own, with Bloom's pill links. Its items know whether they link to the current page:

<<< @/snippets/training/resources/views/menus/nav.blade.php{blade}

## The home page

Bloom's sections map onto the home template's fields:

| Bloom section | Atlas field |
| --- | --- |
| Hero | `title`, `summary` |
| Stats row | `stats` (repeater) |
| Feature grid | `highlights` (repeater, with a Lucide `icon`) |
| Workout cards with tabs | `featured` courses, then a selector per `level` |
| How it works | `steps` (repeater) |
| Testimonials carousel | `testimonials` (repeater) |
| FAQ accordion | `faqs` (repeater) |

The view includes one partial per section. The hero:

<<< @/snippets/training/resources/views/partials/home/hero.blade.php{blade}

The course tabs are BlatUI tabs. The first tab shows the courses the staff picked; the others run a [selector](/basics/selectors) per level. `lynx()->fields->choices('level')` lists the field's options with labels in the visitor's language, so a new level in the admin adds a tab:

<<< @/snippets/training/resources/views/partials/home/courses.blade.php{blade}

The questions are an accordion over a repeater:

<<< @/snippets/training/resources/views/partials/home/faqs.blade.php{blade}

Every course appears as the same card, with its next open session:

<<< @/snippets/training/resources/views/partials/course-card.blade.php{blade}

## The catalogue

The catalogue filters courses by level and by a search, with pagination. A view composer reads the query string, validates it, and builds one selector; the template only prints. Register it in a service provider, and add the provider to `bootstrap/providers.php`:

<<< @/snippets/training/app/Providers/AtlasServiceProvider.php#views{php}

<<< @/snippets/training/app/View/Composers/CourseCatalogue.php#composer{php}

Anything the composer doesn't know, such as `?level=nonsense`, is dropped by validation, and the search loses the characters that would end a selector value early. The filters are links, so they work without JavaScript and keep the language:

<<< @/snippets/training/resources/views/partials/course/filters.blade.php{blade}

## A course and its sessions

The course page shows its curriculum in an accordion, its instructors, and the upcoming sessions, each linking to its own page:

<<< @/snippets/training/resources/views/partials/course/sessions.blade.php{blade}

Prices print with Laravel's `Number::currency()` in the visitor's locale: *€2,400.00* in English, *2 400,00 €* in Portuguese. Dates use Carbon's `isoFormat()` the same way.

## What you get

- **The client:** a finished-looking site in two languages, where every text, menu, figure, testimonial and question is theirs to change, and new courses appear in the catalogue, the tabs and the menus by themselves.
- **You:** BlatUI components you own, plain Blade views that read typed values, and a catalogue built from one selector. No JavaScript build of your own beyond Tailwind and BlatUI's Alpine helpers.
