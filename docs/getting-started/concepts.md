---
title: Core Concepts
description: Pages, templates, fields, languages, routing and actions, explained for Laravel developers.
---

# Core Concepts

This page explains the ideas Lynx is built on, and how they compare with what you know from Laravel. [Your First Site](/getting-started/first-site) puts them to work.

## Pages

Everything a site shows is a **page**: the home page, a blog post, a product, and also things visitors never see as pages of their own, such as a menu or the site's settings.

Pages form one **tree**. Each page has a parent, except the home page at the root, and a position among its siblings. The tree gives you URLs (`/blog/hello-world` is the page `hello-world` under `blog`), breadcrumbs, menus and the structure editors work with in the admin.

Compared with Laravel, a page is like an Eloquent model instance, with one difference: every kind of content lives in the same tree and shares the same features, such as URLs, drafts, revisions, scheduling, permissions and the trash. You don't build those per content type.

```php
$post = lynx()->pages->get('/blog/hello-world');

$post->title;      // a field
$post->url;        // /blog/hello-world
$post->parent();   // the blog page
$post->children(); // the pages under it
```

Each page also has **statuses**: *unpublished* pages are hidden from visitors, *hidden* pages are reachable by URL but left out of lists, and *locked* pages can't be edited until someone unlocks them.

## Templates and fields

A **template** describes a kind of page: which fields it has, where its pages may go in the tree, and whether they have a URL. A **field** is a single piece of content, such as a title, a date, an image or a list of related pages. Fields are created once and shared: the `title` field can belong to the `post`, `blog` and `home` templates.

In Laravel terms, a template is close to a model's schema and a field is close to a column. The difference is that you change them while the site runs, in the admin, in PHP or in YAML files, and Lynx creates each field's table for you. There are no migrations to write.

```php
lynx()->fields->add('summary', 'textarea', translatable: true);
lynx()->templates->add('post', fields: ['title', 'summary']);
```

Lynx has field types for text, rich text, numbers, dates, choices, references to other pages, repeating groups of fields and more; [Templates and Fields](/basics/templates-and-fields) lists them. The page's template also picks its Blade view: pages on `post` render `resources/views/templates/post.blade.php`.

## Reading pages

Pages are not Eloquent models, but reading them will feel familiar. Instead of a query builder, you write a **selector**, a short string that filters, sorts and limits pages:

```php
lynx()->pages->find('template=post, published_on>=2026-01-01, sort=-published_on, limit=10');
```

Selectors compile to a single SQL query. They read the values visitors see in the current language, and they leave out pages the visitor may not see. [Finding Pages](/basics/selectors) covers them in full.

## Languages

Every site has a **default language**, and can have more. A language is a row in a table, not a column, so you can add one to a running site in a single step, without a migration or a deploy.

Fields are either **shared** or **translatable**. A shared field, such as a date or a price, has one value for every language. A translatable field, such as a title, has one value per language.

When a translatable field has no value in the visitor's language, Lynx **falls back** to another language. Each language names its fallback, so Brazilian Portuguese can fall back to Portuguese and Portuguese to English. Fallback happens when the page is read: Lynx never copies values between languages, so a translation added later shows up at once.

A page is **published in a language** once it has a name, and so a URL, in that language. Until then, links to it in that language point to its default language version.

## URLs and routing

Lynx registers one catch-all route after your app's routes. For each request it reads the language from the URL prefix (no prefix for the default language, `/pt` for Portuguese), finds the page by its path, checks that the visitor may see it, and renders its template's view.

Your own routes always win, so a controller at `/account` keeps working and Lynx serves every other URL. When an editor renames or moves a page, its old URL redirects to the new one, in every language.

## Actions

Every change to content or structure is an **action**: `pages.save`, `pages.move`, `fields.create`, `languages.create` and so on. When you call `$page->save()`, the admin saves a form, an Artisan command runs or an AI agent works on the site, they all run the same actions.

The action runner does the same work every time:

1. Validates the input.
2. Checks the user's permissions.
3. Runs the change in a database transaction, with *before* and *after* hooks your code can register.
4. Records what changed, for the activity log, revisions and the admin's review screen.

Any action can run as a **dry run**, which rolls everything back and returns the changes it would have made:

```php
$result = lynx()->pages->delete('/blog', dryRun: true);

$result->changes->summary(); // what would be deleted, page by page
```

In Laravel terms, an action is like a form request and a job in one, with an audit trail. [Actions and Hooks](/deeper/actions-and-hooks) shows how to hook into them and write your own.

## Nothing is lost by accident

Lynx keeps data unless someone deletes it on purpose:

- Removing a field from a template, or switching a page to another template, keeps the values. Switching back brings them back, and only `pages.purge_orphans` deletes them.
- Renamed and moved pages keep their old URLs as redirects.
- Editors move pages to the trash, and can restore them until the trash is emptied. Deleting a page for good is a separate action, `pages.delete`.
- Every save that changes a page's values keeps a revision, so editors can compare and restore earlier versions.
