---
draft: true
title: Templates and Fields
description: Chapter 2 of the bilingual website guide. English and French, the fields each kind of page holds, the templates, and the sections' pages.
---

# Templates and Fields

This chapter decides what each kind of page holds: a lawyer has a name, a role, a photo and an office; a news item has a date and a category. In Lynx you describe this with **fields**, single pieces of content such as a title or a date, and **templates**, the kinds of page, each a list of fields. Together they are the site's *schema*. The admin then gives each kind of page an editing form with exactly those fields.

You can build the schema by clicking through the admin, under **Settings › Fields** and **Settings › Templates**. This guide writes it in PHP instead, as a **seeder**: a Laravel class in `database/seeders` that fills the database when you run `php artisan db:seed`. That way the schema is code you can read, review and run again on a fresh database. The starter's seeder is `database/seeders/SiteSchemaSeeder.php`.

## Two languages

`lynx:install` made one language, with the code `default`. Give it its real code and locale, then add French:

```php
lynx()->languages->update('default', ['code' => 'en', 'name' => 'English', 'locale' => 'en-CA']);
lynx()->languages->add('fr', 'Français', locale: 'fr-CA', fallback: 'en');
```

The **code** (`en`, `fr`) is how your code and the admin name a language. The **locale** (`en-CA`, `fr-CA`) tells Laravel and browsers which variant it is, here Canadian English and Canadian French; it formats dates and picks translations. The **fallback** says what visitors see when a French text is missing: the English one. A French visitor never sees an empty heading while a page waits for its translation.

The default language has no prefix in addresses (`/team/`), and French has its code (`/fr/equipe/`).

## Fields

A field is created once and can be used by several templates. The site's text fields are **translatable**: they hold one value per language. Fields such as a date, a phone number or a choice hold one value that both languages share.

```php
lynx()->fields->add('summary', 'textarea', translatable: true, label: 'Summary');
lynx()->fields->add('body', 'textarea', translatable: true, label: 'Text', settings: ['format' => 'html']);
lynx()->fields->add('photo', 'images', label: 'Photo', settings: ['max_files' => 1]);
lynx()->fields->add('published_on', 'date', label: 'Published on');
```

The first argument is the field's name, which your views use (`$page->summary`). The second is its **type**: `textarea` is longer text, and with `'format' => 'html'` editors get a rich text editor. `images` holds uploaded pictures; `max_files: 1` allows one, so a view reads it as a single image instead of a list. [Field Types](/basics/field-types) lists every type and its settings.

### Choices

A lawyer's role is one of four values. A `choice` field stores the value, and shows each visitor its label in their language:

```php
lynx()->fields->add('role', 'choice', label: 'Role', settings: ['input' => 'radios', 'options' => [
    ['value' => 'founding-partner', 'label' => ['en' => 'Founding partner', 'fr' => 'Associé fondateur']],
    ['value' => 'partner', 'label' => ['en' => 'Partner', 'fr' => 'Associé']],
    ['value' => 'associate', 'label' => ['en' => 'Associate', 'fr' => 'Avocat']],
    ['value' => 'paralegal', 'label' => ['en' => 'Paralegal', 'fr' => 'Parajuriste']],
]]);
```

Pages store the value, `partner`, and your code compares values, so editors can reword a label in either language without breaking anything. `'input' => 'radios'` shows the choice as radio buttons in the editor.

French job titles agree with the person (*Associé* for a man, *Associée* for a woman), which one label per role can't do. So lawyers also get a `job_title` text field, shown instead of the role when it has a value. Chapter 5 shows how the view picks one.

### Links to other pages

Some fields point at other pages. A lawyer works in one office, and a practice area names the lawyers who handle it:

```php
lynx()->fields->add('office', 'page', label: 'Office', settings: ['multiple' => false, 'templates' => ['office'], 'input' => 'select']);
lynx()->fields->add('lawyers', 'page', label: 'Lawyers', settings: ['templates' => ['lawyer']]);
```

A `page` field stores references to pages. `'templates' => ['office']` limits the choice to office pages, and `'multiple' => false` allows one, so `$lawyer->office` reads as that office page. Without it, the field holds a list.

The seeder adds the site's other fields the same way: the home page's `tagline` and `highlights` (a repeater, a list of figures editors add and sort), a lawyer's `job_title`, `species` and `fun_fact`, an office's `city`, `address` and `phone`, and a post's `category` and `practice_areas`. The starter's office pages also have a time zone field, which its meeting room bookings use. This guide leaves it out.

## Templates

A template is a list of fields. A field in the list can be its name, or an array with options for this template only, such as `required`:

```php
$title = ['name' => 'title', 'required' => true];
$searchable = fn (string $name): array => ['name' => $name, 'searchable' => true];

lynx()->templates->update('home', ['add' => ['summary', 'tagline', 'body', 'highlights']]);
lynx()->templates->add('team', fields: [$title, 'summary'], label: 'Team', singleton: true);
lynx()->templates->add('lawyer', fields: [
    $title, ['name' => 'role', 'required' => true], 'job_title', $searchable('species'),
    'photo', $searchable('summary'), $searchable('body'), $searchable('fun_fact'), 'office',
], label: 'Lawyer');
lynx()->templates->add('post', fields: [
    $title, ['name' => 'category', 'required' => true], ['name' => 'published_on', 'required' => true],
    $searchable('summary'), $searchable('body'), 'practice_areas',
], label: 'Post');
```

- `lynx:install` already made the `home` template and the `title` field, so the home template is updated with the fields it adds.
- `singleton: true` allows one page on a template. The site has one team page, one news page and one contact page.
- `searchable` adds a field's words to the search index, so the search page in chapter 6 finds lawyers by their species and fun facts. Titles are always searchable.
- A required field must be filled in the default language before a page can be saved. French can come later.

The starter's seeder adds the other templates the same way: `practice-areas` and `practice-area`, `news`, `offices` and `office`, `contact`, `search`, and `page` for plain pages such as the legal notice.

## The sections' pages

The five sections have one page each. The seeder creates them, with an address and a title in both languages:

```php
$sections = [
    ['practice-areas', 'Practice areas', 'domaines', 'Domaines de pratique'],
    ['team', 'Our team', 'equipe', 'Notre équipe'],
    ['news', 'Cases and news', 'nouvelles', 'Dossiers et nouvelles'],
    ['offices', 'Offices', 'bureaux', 'Bureaux'],
    ['contact', 'Contact', 'contact', 'Contact'],
];

foreach ($sections as [$template, $title, $frenchName, $frenchTitle]) {
    $page = lynx()->pages->new($template, '/');
    $page->name = $template;
    $page->title = $title;
    $page->setLanguageName('fr', $frenchName);
    $page->setLanguageValue('fr', 'title', $frenchTitle);
    $page->save();
}
```

`pages->new()` takes the template and the parent page, here the home page (`/`). A page's **name** is its part of the address, so the team page lives at `/team/`. Setting a field directly (`$page->title = ...`) writes the default language. `setLanguageName()` and `setLanguageValue()` write French, so the same page is `/fr/equipe/` with the title *Notre équipe*.

Run the seeder:

```bash
php artisan db:seed --class=SiteSchemaSeeder
```

Open **Settings › Templates** in the admin to see the templates, and the page tree to see the five sections. Click a section: its editor has a tab per language.

## What you have now

- **The firm:** an editing form per kind of page, with only the fields that kind needs and a tab for each language.
- **You:** the schema in code, which builds the same site on any machine. [Schema and Content Packages](/deeper/schema-and-content) shows how to keep it in step between your machine and the live site.
