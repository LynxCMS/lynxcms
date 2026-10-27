---
draft: true
title: SEO and Launch
description: Chapter 7 of the bilingual website guide. Titles, descriptions and structured data for search engines, the sitemap, the page cache, the admin's look, and deploying.
---

# SEO and Launch

The site is complete. This chapter gets it ready for visitors: tags that make it show well in search results and when shared, a sitemap, the page cache that makes it fast, and the steps to put it on a server.

## Tags for search engines

The layout already has `<x-lynx-seo :page="$page" />` in its `<head>` (chapter 4). For each page it prints the title, the description, the canonical link, the tags social networks read when someone shares a link, and `hreflang` links that tell search engines where the page is in the other language.

Turn on the SEO fields for the templates visitors see. The seeder `database/seeders/SiteFeaturesSeeder.php` does it for every public template:

```php
use LynxCms\Seo\Actions\EnableSeo;

foreach (['home', 'practice-areas', 'practice-area', 'team', 'lawyer', 'news', 'post', 'offices', 'office', 'contact', 'page', 'search'] as $template) {
    lynx()->run(EnableSeo::class, ['template' => $template]);
}
```

`lynx()->run()` runs an **action**: one of Lynx's changes, such as saving a page or, here, adding the SEO fields to a template. Each of these templates now has an **SEO** tab in the page editor, where editors can write a search title and description, pick an image for shared links, or hide a page from search engines. A page that leaves them empty still gets good tags: its title, and its summary as the description.

### The site's settings

The site's name, the pattern of every title and a default description live on a settings page, which editors open from the admin's **SEO** screen. The seeder fills them in both languages:

```php
use LynxCms\Seo\SeoSchema;

$settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
$settings->{SeoSchema::SITE_NAME} = 'Castor & Otters, Inc.';
$settings->setLanguageValue('fr', SeoSchema::SITE_NAME, 'Castor & Otters, inc.');
$settings->{SeoSchema::TITLE_PATTERN} = '{title} · {site}';
$settings->{SeoSchema::DEFAULT_DESCRIPTION} = 'A wildlife law firm in Vancouver and Montreal, for every creature that keeps its feet wet.';
$settings->setLanguageValue('fr', SeoSchema::DEFAULT_DESCRIPTION, 'Un cabinet de droit de la faune à Vancouver et à Montréal, pour toutes les créatures qui ont les pieds dans l’eau.');
$settings->save();
```

`SeoSchema` holds the names of the SEO fields, so your code doesn't spell them out. With this pattern, a lawyer's page is titled *Castor Bélanger · Castor & Otters, Inc.*. The French site name writes *inc.* in lower case, as French usage does.

### Structured data

Search engines read **structured data**, a description of the page in a shared vocabulary ([schema.org](https://schema.org)), to show richer results, such as an office's address and phone number. Lynx prints it for every page. To describe a kind of page, map its template to a schema.org type and say which field holds each property:

```php
use LynxCms\Seo\Actions\MapTemplate;

lynx()->run(MapTemplate::class, ['template' => 'office', 'type' => 'ProfessionalService', 'map' => [
    'name' => '@title',
    'telephone' => 'phone',
    'description' => 'summary',
    'address.streetAddress' => 'address',
    'address.addressLocality' => 'city',
    'address.addressCountry' => '=CA',
]]);
```

A property names a field (`phone`), one of the page's own values (`@title`), or a fixed value after `=`. The starter maps lawyers as `Person` and posts as `NewsArticle` the same way. Editors can change the mappings in the admin's **SEO** screen, under **Structured data**. [SEO](/modules/seo) lists the types and properties.

## The sitemap

With `lynxcms/sitemap` installed, `/sitemap.xml` lists every published page in both languages, each with its address in the other language. Pages that ask search engines not to index them are left out: the search page is one, since the seeder sets its **Hide from search engines** field. Submit the sitemap's address in Google Search Console once the site is live.

## The page cache

Most visitors read the same pages. The **page cache** keeps a copy of each page the first time a visitor who isn't signed in opens it, and sends that copy to the next ones without building the page again. When an editor saves a change, Lynx clears the stored copies: by default all of them, since a menu or a list on any page can show the page that changed. Turn it on in `.env`, Laravel's file of settings for each server:

```dotenv
LYNX_PAGE_CACHE=true
```

Every template is cached by default. Search results depend on the visitor's words, so the seeder turns caching off for the search template:

```php
use LynxCms\Cache\Actions\SavePageCacheRules;

lynx()->run(SavePageCacheRules::class, ['templates' => ['search' => ['cache' => false]]]);
```

A cached copy is the same for every visitor, but a form carries a security token that must be each visitor's own. `@lynxCsrf`, which the layout has in its `<head>`, takes care of it: the cached contact page holds a placeholder, and a small script fetches each visitor's token when the page loads. Signed-in staff never get cached pages, so they always see the live site. [Page Cache](/modules/page-cache) explains how to send cached pages without PHP, which makes the site faster still.

## The admin in the firm's colours

The admin's sign-in screen and sidebar can carry the firm's name, logo and colours. Superusers set them under **Settings › General**; the seeder sets them once:

```php
use LynxCms\Admin\Branding\SaveBranding;

lynx()->run(SaveBranding::class, [
    'name' => 'Castor & Otters',
    'logo' => 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(public_path('images/logo.svg'))),
    'background' => '#f3eee4',
    'accent' => '#2b1d17',
]);
```

## Putting it on a server

The site runs wherever Laravel runs. On the server, set `APP_ENV=production` and `APP_DEBUG=false` in `.env`, keep a queue worker running for the form's emails, and add Laravel's scheduler to cron, the server's timer for recurring commands, so pages scheduled to publish go live on time. Each deploy runs:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan lynx:page-cache --clear --warm
php artisan queue:restart
```

`lynx:page-cache --clear --warm` drops the cached pages, since new views change how pages look, then builds them again so the first visitors don't wait. [Installation](/getting-started/installation#the-scheduler) shows the scheduler's cron line, and [Shared hosting](/getting-started/installation#shared-hosting) covers hosts without root access.

The site's content lives in the database. Once the firm writes on the live site, copy content down to your machine with a content package rather than seeding again; [Working on a Live Site](/deeper/live-site) shows how.

## What you have now

- **The firm:** a fast bilingual site that shows well in search results, with every text, menu and page in their hands.
- **You:** a site built from a schema seeder, plain Blade views and a few lines of configuration, ready to grow. The [Meeting Rooms guide](/guides/meeting-rooms/) builds an app on top of this same site.
