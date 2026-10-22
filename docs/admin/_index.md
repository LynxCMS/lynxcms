---
title: The admin
description: The Livewire admin, its screens and how to theme it.
order: 6
---

# The admin

`lynxcms/admin` adds a Livewire admin at `/lynx` (`LYNX_ADMIN_PREFIX`).

| Screen | Does | Needs |
| --- | --- | --- |
| Pages | The page tree: expand, page, drag to sort or move, quick actions. | `pages.view` |
| Page editor | Fields per language, settings, drafts, history, schedule. | `pages.edit` |
| Menus | The menu builder. | `menus.manage` |
| SEO | SEO fields per template, structured data, an audit of titles and descriptions, the site settings. | `seo.manage` |
| Sitemap | URLs per file, grouping, templates left out, the parts and their cache. | `sitemap.manage` |
| Templates, Fields | The schema. | `schema.manage` |
| Languages | Add, edit and delete languages; tab labels. | `schema.manage` |
| Roles, Users | Roles, their grants and who holds them; invitations. | superuser |
| Account security | Password, two-factor authentication and passkeys. | signed in |

Superusers also see developer details in the page editor: the template's name, each field's type, and on the Texts tab each string's key and the view that declares it. Other users see only the labels.

## Search

The box in the top bar (Cmd or Ctrl+K) finds pages. Plain text searches titles and the fields templates mark searchable (see [Search](/docs/content/fields-and-templates#search)), matching word beginnings in any order. Anything with an operator (`=`, `<`, `>`) runs as a [selector](/docs/content/selectors), the same one `lynx()->pages->find()` takes:

```
template=course, price>100, sort=-modified
```

Hidden and unpublished pages are included unless the selector sets `include=`. It shows 10 results unless the selector sets `limit=` (50 at most), and only pages the user may view. A selector that cannot run shows why.

The icon at the right of the top bar opens the site in a new tab. In the page editor it opens the page being edited.

- [Sign-in and accounts](/docs/admin/sign-in)
- [Roles and permissions](/docs/admin/permissions)
- [Extending the admin](/docs/admin/extending)

## Look and feel

The admin is built on BlatUI components. Change its look without rebuilding anything in `config/lynx-admin.php`:

```php
'theme' => 'system', // light, dark or system
'appearance' => [
    'base' => 'stone', 'theme' => 'cyan', 'radius' => '0.3', 'font' => 'inter',
    'heading_font' => 'mono', 'shadow' => 'none', 'spacing' => 'default', 'tracking' => 'wide',
],
'stylesheets' => [], // extra CSS that redefines BlatUI tokens
```

Each option also has an environment variable (`LYNX_ADMIN_ACCENT`, `LYNX_ADMIN_RADIUS` and so on).

`theme` is the default. Each user can pick light, dark or system in the user menu at the bottom of the sidebar, which is remembered in that browser. The same menu links to the account's security settings.

Set `LYNX_ADMIN_SCHEMA_EDITING=false` to make the schema screens read only, for sites whose schema comes from [schema files](/docs/content/schema-and-content).
