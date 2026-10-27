---
draft: true
title: Content in Two Languages
description: Chapter 3 of the bilingual website guide. The firm's pages written in English and French from content files, pages that point at other pages, and what editors do in the admin.
---

# Content in Two Languages

The site needs content before its views are worth writing: lawyers, practice areas, news and two offices, in English and French. On a real project the firm's staff would type it in the admin. Here a second seeder writes it, so you get a full site with one command, and you see how code writes pages in two languages.

## Content as data

The starter keeps the firm's words in plain PHP files, one per section, in `database/seeders/content`. Each entry is a page: its name, the fields both languages share, and the text in each language:

```php
// database/seeders/content/team.php (one lawyer of ten)
return [
    [
        'name' => 'castor-belanger', 'role' => 'founding-partner', 'office' => 'vancouver',
        'en' => [
            'title' => 'Castor Bélanger',
            'species' => 'North American beaver',
            'summary' => 'Has built more structures without a permit than anyone in the province, and has defended every one of them.',
        ],
        'fr' => [
            'species' => 'Castor du Canada',
            'summary' => 'A bâti plus d’ouvrages sans permis que quiconque dans la province, et les a tous défendus.',
        ],
    ],
];
```

The French part has no `title`. Castor's name is the same in both languages, so French falls back to the English value, as chapter 2 set up. Write a French value only where the French text differs.

## Writing pages in both languages

The seeder (`database/seeders/SiteContentSeeder.php`) turns each entry into a page. One small method writes an entry's values, whatever the template:

```php
private function write(Page $page, array $content): void
{
    foreach ($content['en'] as $field => $value) {
        $page->{$field} = $value;
    }

    foreach ($content['fr'] as $field => $value) {
        $page->setLanguageValue('fr', $field, $value);
    }
}
```

Assigning a field writes the default language. `setLanguageValue()` writes another one. Saving the page stores both at once.

A lawyer's page is then:

```php
foreach ($this->content('team') as $lawyer) {
    $page = lynx()->pages->new('lawyer', '/team/');
    $page->name = $lawyer['name'];
    $page->setLanguageName('fr', $lawyer['name']);
    $this->write($page, $lawyer);
    $page->role = $lawyer['role'];
    $page->office = [lynx()->pages->get("/offices/{$lawyer['office']}/")->id];
    $page->save();
}
```

The role is a choice, so the page stores its value. The office is a page field, so the page stores a list of page ids, here one: the id of the office page found by its address. `lynx()->pages->get()` finds a page by its address, always written in the default language.

## The offices

The lawyers point at offices, so the offices come first. Each office is a page under `/offices/` with its city, address and phone:

```php
private function office(string $name, string $title, string $city, string $address, string $phone): Page
{
    $office = lynx()->pages->new('office', '/offices/');
    $office->name = $name;
    $office->setLanguageName('fr', $name);
    $office->title = $title;
    $office->setLanguageValue('fr', 'title', $city);
    $office->city = $city;
    $office->address = $address;
    $office->phone = $phone;

    return $office->save();
}

$this->office('vancouver', 'Vancouver', 'Vancouver', "1 Beaver Lodge Way\nVancouver, BC V6B 1A1", '+1 604 555 0142');
$this->office('montreal', 'Montreal', 'Montréal', "200, rue de la Loutre\nMontréal (Québec) H2Y 1C6", '+1 514 555 0187');
```

The city, address and phone are shared fields: an address is the same in both languages. The title is translatable, so Montreal can be *Montréal* in French. In the starter, the rooms seeder creates the offices, since rooms belong to them too; a website without rooms creates them here.

## Pages that name other pages

Practice areas name the lawyers who handle them, by their page names:

```php
// database/seeders/content/practice-areas.php
['name' => 'water-rights', 'fr_name' => 'droit-de-l-eau',
 'lawyers' => ['castor-belanger', 'theo-tortue', 'marty-rat-musque'], 'en' => [...], 'fr' => [...]],
```

The seeder turns the names into page ids, in that order, which is the order the site lists them:

```php
$page->lawyers = array_map(fn (string $name): int => (int) lynx()->pages->get("/team/{$name}/")->id, $area['lawyers']);
```

Lawyers come before practice areas, and practice areas before the news that links to them, so every page a field points at already exists.

Posts work the same way, with their date and category. A date field takes a date written as text, such as `'2026-03-12'`, and a view reads it back as a date object it can format in the visitor's language (chapter 5).

## The home page's figures

The home page shows three figures, such as *4 generations* of lawyers. They are a **repeater**: a list of items, each with the same fields, that editors add, sort and switch off. The list is shared, and each item's text has a value per language:

```php
$home->highlights = [
    ['values' => [
        'figure' => ['en' => '4 generations', 'fr' => '4 générations'],
        'figure_note' => ['en' => 'of lawyers who could swim before they could read', 'fr' => 'd’avocats qui nageaient avant de savoir lire'],
    ]],
    // two more figures
];
```

## Running it

```bash
php artisan db:seed --class=SiteContentSeeder
```

The starter's `DatabaseSeeder` runs both seeders in order, with `lynx:install`, so `php artisan migrate:fresh --seed` builds the whole site from an empty database.

## What editors do

Open a lawyer in the admin's page tree. Each translatable field has a tab per language, and French fields that hold no value show the English text they fall back to. The page's **Settings** tab holds its address in each language. Editors who add a lawyer next year do the same as this seeder: fill in the English, then the French where it differs.

## What you have now

- **The firm:** ten lawyers, five practice areas, six cases and news items and two offices, in both languages.
- **You:** content in files you can read and change, written through the same API your own code and imports can use.
