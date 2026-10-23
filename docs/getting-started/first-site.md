---
title: Your First Site
description: Build a small blog in two languages, from templates and fields to Blade views and translated URLs.
---

# Your First Site

In this tutorial you build a small blog with Lynx: a home page that shows the latest posts, a blog page that lists them all, a page for each post, and a Portuguese version of the site. Along the way you meet the ideas you use on every Lynx site: templates, fields, pages, views, selectors and languages.

It takes about thirty minutes. You need a Laravel app with Lynx installed and a user who can sign in to the admin, as [Installation](/getting-started/installation) describes.

> [!TIP]
> Every code sample on this page is a real file in the Lynx repository, and Lynx's test suite runs them on each change, so what you read here works with the current release.

## Planning the content

A Lynx site is a tree of pages. Ours will look like this:

```
/                     Home       (template: home)
└── /blog             Blog       (template: blog)
    ├── /blog/hello-world        (template: post)
    └── /blog/second-post        (template: post)
```

Each page uses a **template**, which says what the page holds. A template is a list of **fields**. If you think in Laravel terms, a template is close to a model's schema and a field is close to a column, except that you create them at runtime, without migrations.

Our blog needs two templates:

| Template | Fields | Notes |
| --- | --- | --- |
| `post` | `title`, `summary`, `body`, `published_on` | A blog post. Lives under the blog page. |
| `blog` | `title`, `summary` | The page that lists posts. The site has only one. |

The `home` template and the `title` field already exist: `lynx:install` created them along with the home page.

## Creating the fields and templates

You can build fields and templates in the admin, under **Settings › Fields** and **Settings › Templates**, or in PHP. Both run the same actions, so the result is the same. This tutorial uses PHP so you can copy it; open Tinker with `php artisan tinker` and run:

<<< @/snippets/first-site/schema.php#schema{php}

Here is what each call does:

- `fields->add()` creates a field, and the table that stores its values. `translatable: true` gives the field one value per language, which you will use later in this tutorial. The `date` field is shared: a post has one publication date in every language.
- The `textarea` type with `'format' => 'html'` gives editors a rich text editor, and Lynx cleans the HTML on every save.
- `templates->add()` creates a template from fields. Marking `title` as required means a post can't be saved without one. `singleton: true` allows only one page on the `blog` template.
- `templates->update()` sets the **family rules**: blog pages hold posts, and posts live under the blog. The admin follows these rules when an editor adds a page.

Open **Settings › Templates** in the admin to see both templates, and click one to see its fields.

### Keeping the schema in Git

Fields and templates live in the database, but you can keep them in your repository like migrations. Export them as YAML files:

```bash
php artisan lynx:schema:export
```

This writes one file per language, field, template and role to `lynx/schema`. Commit the folder, and on another machine (a teammate's, or production) apply it:

```bash
php artisan lynx:schema:apply
```

[Schema and Content Packages](/deeper/schema-and-content) covers the details.

## Creating pages

Editors create pages in the admin: in the page tree, open the actions of the **Home** row and choose **New**, then pick the template. The list of templates only offers the ones allowed in that place. Earlier you set `child_templates` to `post` on the blog template, so when an editor creates a page under the blog page, **Post** is the only template on offer, and they can't put a post anywhere else.

For this tutorial, create the blog page and a first post in Tinker:

<<< @/snippets/first-site/pages.php#pages{php}

`pages->new()` takes a template and a parent: the home page is `/`, and the blog page is `/blog`. The page's `name` becomes its URL segment, so the post's URL is `/blog/hello-world`. Fields are properties: assign them and call `save()`.

Create a second post the same way, or in the admin, so the blog has something to list. Then open `/blog/hello-world` in your browser: Lynx shows the page with a plain built-in view, because you haven't written one yet.

## Writing views

A page renders the Blade view named after its template, in `resources/views/templates`. Each view receives the page as `$page`.

### A layout

Start with a layout, as an [anonymous Blade component](https://laravel.com/docs/blade#anonymous-components) in `resources/views/components/layout.blade.php`:

<<< @/snippets/first-site/views/components/layout.blade.php{blade}

- `lynx()->languages->current()` is the language of the request; its locale goes in the `lang` attribute.
- `@hreflang($page)` adds links to the page in its other languages, for search engines.
- `lynx()->pages->get('/blog')` finds a page by its path; `->url` is its URL in the current language.
- The language switcher shows up once the site has a second language. You will add one at the end of this tutorial.

### The post

Create `resources/views/templates/post.blade.php`:

<<< @/snippets/first-site/views/templates/post.blade.php{blade}

`@field($page->title)` prints a field's value. It escapes text, prints rich text from html fields as HTML, and marks text that comes from another language, which matters once the site has translations. A `date` field reads as a Carbon date, so you can format it as usual. The `?->` is there because a post without a date reads as `null`.

### The blog page

Create `resources/views/templates/blog.blade.php`:

<<< @/snippets/first-site/views/templates/blog.blade.php{blade}

`$page->children()` returns the pages under this one. Its argument is a **selector**: a short query that filters, sorts and limits pages. `sort=-published_on` sorts by the date field, newest first (the `-` means descending), and `limit=20` stops after twenty posts.

### The home page

Finally, replace the home page's view, `resources/views/templates/home.blade.php`:

<<< @/snippets/first-site/views/templates/home.blade.php{blade}

`lynx()->pages->find()` runs a selector over the whole site. You can call it anywhere: in views, controllers, Livewire components or jobs. [Finding Pages](/basics/selectors) lists everything selectors can do.

Visit `/`, `/blog` and `/blog/hello-world`: the site is complete in one language.

## Adding a second language

Now make the blog multilingual. Add Portuguese in the admin under **Settings › Languages**, or in Tinker:

<<< @/snippets/first-site/languages.php#add-language{php}

The first line gives the default language, which `lynx:install` called "Default" with the code `default`, its real code, name and locale. The second adds Portuguese, with English as its **fallback**: whenever a Portuguese value is missing, Lynx shows the English one.

Translate the blog page and the first post. In the admin, open the page and use the language tabs above each field; give the page a Portuguese URL name in its **Settings** tab. Or in Tinker:

<<< @/snippets/first-site/languages.php#translate{php}

Now open `/pt/blogue/ola-mundo`:

- The title is in Portuguese.
- The body has no Portuguese translation, so it shows in English. `@field` marks it with `lang="en"`, so browsers and screen readers read it correctly.
- The layout shows the language switcher, which links to the post in each language it is published in.

On `/pt/blogue`, the second post appears with its English URL, `/blog/second-post`. A page is published in a language once it has a name in that language, so until an editor translates it, Portuguese visitors are sent to the English page instead of a missing one.

## Where to go next

You have used the core of Lynx: templates and fields for the structure, pages for the content, Blade views and selectors for the front end, and languages with fallback. From here:

- [Core Concepts](/getting-started/concepts) explains the ideas behind what you just did.
- [Templates and Fields](/basics/templates-and-fields) covers every field type, layouts and conditions.
- [Views](/basics/views) covers images, menus, editable texts and more Blade helpers.
- [Menus](/modules/menus), [SEO](/modules/seo) and [Forms](/modules/forms) are modules most sites add next.
