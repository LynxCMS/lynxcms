---
title: Core Concepts
description: Pages, templates, fields, languages, routing and actions, the ideas every Lynx site is built on.
---

# Core Concepts

Lynx is a content management system (CMS) built on Laravel. It can be the whole site, in a new Laravel app that does nothing else, or you can add it to an app you already have. People write and publish the site's content in a web admin, and your Blade views show it to visitors, in as many languages as the site needs.

This page explains the ideas Lynx is built on: pages, templates and fields, selectors, languages and actions. Each section says what the idea lets you do, shows a little code, then explains how it works. You don't need to remember all of it now. [Your First Site](/getting-started/first-site) puts these ideas to work step by step.

## Who does what

Two kinds of people work on a Lynx site:

- **You, the developer**, decide what kinds of content the site has and how each one looks: a blog post has a title, a summary and a body, and this Blade view shows it.
- **Editors** write the content in the admin, at `/lynx` on your site. They add posts, upload images, translate pages and publish them, without touching your code or asking you to change a comma on a Friday afternoon.

The editor role Lynx creates can change pages but not templates or fields, so the fields your views print are always there.

You can manage content without the admin, too. `php artisan tinker` opens Tinker, Laravel's interactive PHP shell, where you can run the PHP examples on this page against your site: create pages, translate them, find them with a selector. Lynx's Artisan commands export and import content, apply schema files and more ([Artisan Commands](/reference/artisan)).

## Pages

A Lynx site is made of **pages**. A blog post is a page, the blog that lists the posts is a page, and so is the home page. Content that visitors never open on its own, such as a menu or the site's settings, is stored as a page too. That sounds like a filing system designed by someone who owned one folder, but it means a menu gets the same editing screen, history and permissions as everything else.

Pages sit in a **tree**, like files in folders: every page except the home page has a parent page. The tree gives each page its address. The page `hello-world` under the page `blog` lives at `/blog/hello-world`. Breadcrumbs, menus and the list of pages in the admin follow the same tree.

Since every kind of content is a page, the features pages have work for all of it: addresses, drafts, revisions (the saved history of a page), publishing on a schedule, permissions and a trash. You don't build them again for posts, products or events.

In PHP, you get a page by its address and read its content as properties:

```php
$post = lynx()->pages->get('/blog/hello-world');

$post->title;      // a field
$post->url;        // /blog/hello-world
$post->parent();   // the blog page
$post->children(); // the pages under it
```

A page can also have **statuses**, which change who sees it:

- *Unpublished*: visitors can't open it, or any page below it.
- *Hidden*: visitors can open it at its address, but it stays out of lists, menus and search.
- *Locked*: nobody can change, move or delete it until someone unlocks it, however urgent they say it is.

## Templates and fields

A **template** is a kind of page, such as `post` or `course`. A **field** is one piece of content on a page: a title, a date, an image, a list of related pages. A template is a list of fields, so every `post` page has the same fields: a title, a summary and a publication date.

You create fields and templates while the site runs, in the admin, in PHP or in YAML files, and Lynx creates the database tables that hold them. You write no migrations (the PHP files Laravel apps use to change the structure of the database):

```php
lynx()->fields->add('summary', 'textarea', translatable: true);
lynx()->fields->add('published_on', 'date');

lynx()->templates->add('post', fields: ['title', 'summary', 'published_on']);
```

A field is created once and can belong to several templates. The `title` field, for example, belongs to the `home`, `blog` and `post` templates.

The template also decides how its pages look. Pages on the `post` template are rendered with the Blade view `resources/views/templates/post.blade.php`. Blade is Laravel's template language: HTML with tags such as `{{ $page->title }}` to print values.

Lynx has field types for text, rich text, numbers, dates, choices, references to other pages, repeating groups of fields and more. [Field Types](/basics/field-types) lists them.

## Reading pages

To list pages, you describe the ones you want with a **selector**: a short text that says which pages, in what order and how many.

```php
lynx()->pages->find('template=post, published_on>=2026-01-01, sort=-published_on, limit=10');
```

This one reads as "pages on the `post` template, published on 1 January 2026 or later, newest first, at most ten". Each part between commas is a condition, and a page must match all of them.

In a view, you loop over the result with `@foreach`:

```blade
@foreach (lynx()->pages->find('template=post, sort=-published_on, limit=10') as $post)
    <a href="{{ $post->url }}">{{ $post->title }}</a>
@endforeach
```

A selector reads each value in the visitor's language, and leaves out pages the visitor may not see, such as unpublished ones, so the view doesn't check either. Each selector runs as a single database query, so it stays fast on large sites. [Finding Pages](/basics/selectors) lists everything a selector can do.

## Languages

Every site starts with one language, called the **default language**. You can add more at any time, even to a site that is already live. Adding a language takes one step, and needs no change to the database's structure and no deploy:

```php
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'en');
```

Each field is either **translatable** or **shared**. A translatable field, such as a title or a body text, has one value per language. A shared field, such as a date or a price, has one value for every language, so you enter it once.

Your views don't change when you add a language. `{{ $page->title }}` prints the title in the visitor's language: the English title on `/blog/hello-world`, the Portuguese one on `/pt/blogue/ola-mundo`.

A page is rarely translated all at once (translators, like everyone else, stop for lunch), so each language names a **fallback**: the language to borrow from when a value is missing. Portuguese can fall back to English, and Brazilian Portuguese to Portuguese and then English. A Portuguese visitor then sees the English title until someone writes the Portuguese one. As soon as it is saved, they see it. Lynx looks up the fallback every time it shows a page and never copies values between languages, so there is nothing to keep in sync.

A page appears in a language once it has an address in that language, such as `/pt/blogue/ola-mundo`. Until then, links to it in that language lead to its default language version, so visitors never reach a missing page.

## URLs and routing

A Laravel app decides which code answers a URL with **routes**. Lynx adds one route of its own, after your app's routes, that answers every URL they don't. For each request it:

1. reads the language from the start of the URL: no prefix for the default language, `/pt` for Portuguese;
2. finds the page at that address;
3. checks that the visitor may see it;
4. renders the page with its template's view.

Your own routes keep working. A controller of yours at `/account` still answers `/account`, and Lynx serves every other URL. When an editor renames or moves a page, its old address redirects to the new one, in every language, so bookmarks, old newsletters and that forum post from 2019 keep working.

## Actions

Every change to content or structure is an **action**: saving a page, moving it, adding a field, adding a language. Each action has a name, such as `pages.save`, `pages.move`, `fields.create` or `languages.create`.

An editor in the admin, your code calling `$page->save()`, an Artisan command (Laravel's command line, `php artisan ...`) and an AI agent all run the same actions, so the same rules apply to all of them. Each time, Lynx:

1. checks that the input is valid;
2. checks that the user is allowed to make the change;
3. makes the change inside a database transaction, so either all of it is saved or none of it, and runs any *hooks*, code of yours that runs before or after the action;
4. records what changed, for the activity log, the page's history and the admin's review screen.

Any action can also run as a **dry run**: Lynx does all the work, reports what would change, then undoes it. Use it to preview a change before you make it, particularly one that starts with `delete`:

```php
$result = lynx()->pages->delete('/blog', dryRun: true);

$result->changes->summary(); // what would be deleted, page by page
```

[Actions and Hooks](/deeper/actions-and-hooks) shows how to add your own rules to actions, and how to write actions of your own.

## Nothing is lost by accident

Lynx assumes nobody meant to delete anything until they do it on purpose:

- If you remove a field from a template, or switch a page to another template, the values already saved are kept but not shown. Add the field back, or switch back, and they return. Only `pages.purge_orphans` deletes them for good.
- When a page is renamed or moved, its old address redirects to the new one.
- Deleting a page in the admin moves it to the trash, where editors can restore it until the trash is emptied. Deleting a page for good is a separate action, `pages.delete`.
- Every save that changes a page's values keeps a revision, a copy of the page as it was, so editors can compare versions and bring back an earlier one.

## Where these ideas come from

Many of these ideas come from [ProcessWire](https://processwire.com), a free content management system (CMS) and framework (CMF) for PHP: content as one tree of pages, templates and fields you create while the site runs, and selectors for finding pages. Lynx brings them into a Laravel app, so you use them together with everything Laravel already gives you: routes, controllers, queues, mail, and its packages.

The API reads the same way. `lynx()->pages->get()`, `lynx()->pages->find()` and `$page->children()` follow ProcessWire's `$pages->get()`, `$pages->find()` and `$page->children()`. If you have used ProcessWire, Lynx will feel familiar.
