---
title: The admin
description: The Livewire admin, its screens and how to theme it.
order: 6
---

# The admin

`lynxcms/admin` adds an admin at `/lynx` (`LYNX_ADMIN_PREFIX`) where editors work without technical knowledge: a page tree they drag to reorder, a page editor laid out per template with a tab per language, drafts, scheduling, history and a trash. It is built with Livewire 4 and [Laravel Fortify](https://laravel.com/docs/fortify) (Laravel's sign-in package), and it is optional: a site can run on `lynxcms/core` alone and manage content from code, files or AI tools (see the [introduction](/getting-started/introduction#running-without-the-admin)).

| Screen | Does | Needs |
| --- | --- | --- |
| Pages | The page tree: expand, page, drag to sort or move, quick actions, and a Details view with each page's languages, status and last change. | `pages.view` |
| Page editor | Fields per language, settings, drafts, history, schedule. | `pages.edit` |
| Menus | The menu builder. | `menus.manage` |
| SEO | SEO fields per template, structured data, an audit of titles and descriptions, the site settings. | `seo.manage` |
| Sitemap | URLs per file, grouping, templates left out, the parts and their cache. | `sitemap.manage` |
| Templates, Fields | The schema. | `schema.manage` |
| Languages | Add, edit and delete languages; tab labels. | `schema.manage` |
| Roles, Users | Roles, their grants and who holds them; invitations. | superuser |
| Account security | Password, two-factor authentication and passkeys. | signed in |

The page tree shows titles by default. Its **Details** view adds, beside each title, the page's state in every language (the same facets as the editor's language tabs: its own title, another language's title, or not published there), its status and its last change. Each user's choice lasts for the session. Above it, filters list the pages with **Draft changes**, the **Scheduled** ones, and those **Not in** each other language, each with its count. A filter shows its pages in one list, most recently changed first, each saying which page it sits under. The filters are [selectors](/basics/selectors#status) (`has_draft=1`, `has_schedule=1`, `language!=pt`), so your code can ask the same questions.

The page editor puts each field's label above its input, and beside the fields a panel shows whether the page is published or has a draft, how much of it each language has its own text for, and its latest activity (the Settings tab has all of it). Templates that lay out their own sidebar keep it instead of the panel.

Superusers also see developer details in the page editor: the template's name, each field's type, and on the Texts tab each string's key and the view that declares it. Other users see only the labels.

## Search

The box in the top bar (Cmd or Ctrl+K) finds pages. Plain text searches titles and the fields templates mark searchable (see [Search](/basics/templates-and-fields#making-fields-searchable)), matching word beginnings in any order. Anything with an operator (`=`, `<`, `>`) runs as a [selector](/basics/selectors), the same one `lynx()->pages->find()` takes:

```
template=course, price>100, sort=-modified
```

Hidden and unpublished pages are included unless the selector sets `include=`. It shows 10 results unless the selector sets `limit=` (50 at most), and only pages the user may view. A selector that cannot run shows why.

The icon at the right of the top bar opens the site in a new tab. In the page editor it opens the page being edited.

- [Sign-in and accounts](/admin/sign-in)
- [Roles and permissions](/admin/permissions)
- [Extending the admin](/admin/extending)

## The admin's language

Each editor can read the admin in their own language: open the menu with your name in the sidebar and pick one under **Language**. The admin remembers the choice for that account. It changes the admin's wording only, so a Portuguese editor can work on an English site. Lynx ships English and Portuguese.

Editors who haven't chosen see the default. Set it with an environment variable, or leave it empty to use the app's locale:

```ini
LYNX_ADMIN_LOCALE=pt
```

To offer another language, list it in `config/lynx-admin.php`, with its name in that language, and add its files in `lang/vendor/lynx-admin/{code}`. Copy the English ones from `vendor/lynxcms/admin/lang/en`. A line you leave out shows in English.

```php
'locales' => [
    'en' => 'English',
    'pt' => 'Português',
    'fr' => 'Français',
],
```

The same folder changes a line of Lynx's wording: `lang/vendor/lynx-admin/en/auth.php` holding only the lines you want to reword. With a single language in `locales`, the menu leaves the choice out.

## Look and feel

The admin is built on BlatUI components. Change its look without rebuilding anything in `config/lynx-admin.php`:

```php
'theme' => 'system', // light, dark or system
'appearance' => [
    'base' => 'stone', 'theme' => 'cyan', 'radius' => '0.3', 'font' => 'geist',
    'heading_font' => 'sans', 'shadow' => 'none', 'spacing' => 'default', 'tracking' => 'wide',
],
'stylesheets' => [], // extra CSS that redefines BlatUI tokens
```

Each option also has an environment variable (`LYNX_ADMIN_ACCENT`, `LYNX_ADMIN_RADIUS` and so on).

### The site's name, logo and colours

Superusers give the admin the site's own look under **Settings > General**: a name, a logo (PNG, JPEG, WebP or SVG, up to 1 MB) and two colours, the sign-in screens' background and an accent for buttons, links and focus rings. The sign-in, password reset, two-factor and invitation screens show them, and so does the top of the sidebar, where a collapsed sidebar shows the name's initial instead of a wide logo. The name also ends every browser tab's title. A preview shows the sign-in card as the fields are filled in.

Until the site has a name or a logo of its own, the admin wears Lynx's: the Lynx mark beside the name, and the Lynx icon in browser tabs. Setting a name or a logo replaces both, so a client never sees Lynx's brand in their admin. Without a logo, the name's initial takes the mark's place, and tabs show the site's own `/favicon.ico`. An accent or a background alone keeps the Lynx mark.

The defaults come from `config/lynx-admin.php`, so a developer can ship them with the site:

```php
'branding' => [
    'name' => 'Castor & Otters',
    'logo' => '/images/logo.svg', // a URL or a path below public/
    'background' => '#f3eee4',
    'accent' => '#1f5a69',
],
```

What a superuser saves wins over the config; an empty field goes back to it. Uploaded logos are kept in private storage and served by the admin, so no `storage:link` is needed; an uploaded SVG is served sandboxed, so it cannot run scripts. The same settings can be changed with the `admin.branding` action, from code or an agent:

```php
use LynxCms\Admin\Branding\SaveBranding;

lynx()->run(SaveBranding::class, ['name' => 'Castor & Otters', 'accent' => '#1f5a69']);
```

`theme` is the default. Each user can pick light, dark or system in the user menu at the bottom of the sidebar, which is remembered in that browser. The same menu links to the account's security settings.

On a site with more than one language, the same menu has **Translation hints**. Each language tab already marks a field's state (filled for its own text, light when it shows another language's, an outline when empty); with hints on, a sentence under the field also says which language visitors see instead.

Set `LYNX_ADMIN_SCHEMA_EDITING=false` to make the schema screens read only, for sites whose schema comes from [schema files](/deeper/schema-and-content).
