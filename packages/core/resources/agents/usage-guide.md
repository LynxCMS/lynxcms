# Working with a Lynx site

Lynx is a multilingual CMS. Everything is a **page** in a tree. Each page uses a **template**, which is an ordered list of **fields**. Languages are rows: there is always a **default** language, and every other language has a fallback chain that ends at the default.

## Before you change anything

1. Call `describe_schema` to see languages, templates, their fields and field types, and registered components.
2. Find pages with `find_pages` (selectors) and read them with `get_page`.
   Before renaming, moving or deleting a page, call `get_page_history`: it lists the page's old URLs (and whether each still redirects), the pages that reference it, menu items included, and its latest activity.
3. Run every write tool with `"dry_run": true` first. Show the user the returned `changes`, then run it again without `dry_run` once they agree. Tools marked DESTRUCTIVE remove data: always ask first.

## Saving safely

- `get_page` returns the page's `revision`. Pass it as `expected_revision` to `pages_save`: if someone saved the page in between, the save is refused with a `conflict` error instead of overwriting their work. Load the page again, merge, and retry.

## Translations

- Translatable fields store one value per language. A missing value falls back through the language chain, field by field. `null` removes a value (it will fall back); `""` stores an explicit empty value.
- Page URL names (`names`) are per language: `{"default": "risk-management", "pt": "gestao-de-risco"}`. A page is only published in a non-default language once it has a name there.
- To translate a page, use `pages_translate` when it is available: it translates only missing values and marks them as machine translations for review. Never overwrite human translations unless the user asks.

## Building structure

- Create fields with `fields_create` (see `field_types` in the schema for valid types and settings), then templates with `templates_create`. Use `"from": "<template>"` to copy another template's fields, then list new fields in `fields`.
- Required fields are validated in the default language only.
- Text and textarea fields can list text formatters in their settings (`"formatters": ["markdown", "smartypants"]`), run in order when a page is shown; `describe_schema` lists them per field type. They never change stored values: tools always read and write the raw value.
- A template field can have conditions, written like ProcessWire's showIf: `show_if` shows the field only while it matches, and `required_if` makes it required while it matches; a hidden field is never required. Clauses separated by commas must all match, `|` separates alternatives, an empty value means empty: `{"name": "venue", "required": true, "show_if": "format=onsite|hybrid"}`, `{"name": "summary", "required_if": "format=online, price>0"}`. Operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `%=` (contains). Conditions read the default language and may only name other fields of the same template.
- Settings and reusable content live on pages whose template has `"has_url": false` (and `"singleton": true` for one-off settings pages).
- Templates may limit where their pages go (shown by `describe_schema`): `allow_children: false` means pages using it cannot have children, `child_templates` lists the templates its children may use, and `parent_templates` the templates its parent may use (empty lists mean any). `pages_save`, `pages_move` and `pages_switch_template` refuse placements these rules forbid. `allowed_templates` is different: the templates a page may switch to.

## Creating pages

`pages_save` with `template`, `parent` (id or default-language path such as `/courses/`), `names` and `values`. Translatable values may be given per language: `{"title": {"default": "Risk", "pt": "Risco"}}`.

## Ordering, moving and status

- Locked pages cannot be saved, moved, sorted, translated, lose old URLs, switched to another template or deleted until unlocked with `pages_status` and `"remove": ["locked"]`. System pages (the CMS or a module needs them) cannot be deleted, moved, switched to another template or have their status changed; their values may be edited.

- Unpublishing a page takes it and every page below it offline: they return 404 and selectors leave them out unless `include=unpublished` or `include=all` is given. Hidden pages stay reachable by URL, are only left out of listings, and do not affect their children.

- `pages_sort` changes a page's position among its siblings (`position` is 0-based; values past the end place it last). It never changes paths.
- `pages_move` changes the parent. Give `sort` to choose the 0-based position among the new siblings, or omit it to place the page last. Moving to the current parent is rejected: use `pages_sort`.
- `pages_status` adds or removes `hidden`, `unpublished`, `locked` and `unique` without saving field values, e.g. `{"page": "/courses/risk/", "remove": ["unpublished"]}` publishes a page. A `unique` page's default-language name is used by no other page anywhere, so adding the flag fails while another page has that name, and creating or renaming another page to it fails too.
- While someone has a page open in the admin editor, writes to it are refused with "is being edited by …". Try again later; do not work around it.
- `get_page_history` lists a page's revisions (newest first); `pages_restore_revision` with a revision id puts those values back as a new save, so it can be undone too.
- To prepare changes without making them live, use `pages_save_draft` (same `values` as `pages_save`), then `pages_publish_draft`; `pages_discard_draft` drops it. A page has at most one draft, shared by everyone.
- `pages_schedule` publishes or unpublishes a page in one language at a future time, e.g. `{"page": "/courses/risk/", "language": "pt", "publish_at": "2026-11-01T09:00:00+00:00"}`; null clears an entry. It is applied within a minute.
- Prefer `pages_trash` over `pages_delete`: it moves a page and the pages under it to the trash, where visitors cannot reach them, and `pages_restore` puts them back (give `parent` when the original place is gone or itself in the trash). `pages_empty_trash` deletes trashed branches for good, all or one (`page`); branches holding a locked page are kept and listed under `blocked` with the locked page ids, so they can be unlocked first.
- Renaming or moving a page keeps its old URLs as redirects. `redirects_delete` removes one so it stops redirecting, e.g. `{"page": "/courses/risk/", "path": "courses/old-risk", "language": "pt"}` (path without language prefix; language defaults to the default one). The current URL cannot be deleted, and locked pages refuse.

## Template texts

Templates can declare short texts editors may change (headlines, button labels, footer notes). `get_page` lists them under `strings` with the template's own text (`default`) and any edits by language. Change them with `strings_save`: `{"page": "/", "language": "pt", "values": {"hero.title": "Produções audiovisuais"}}`; `null` puts the template's text back. Site strings (`"scope": "site"`) are shared by every page.

## Menus

- `menus_create` makes a menu (`name` such as "top", `title` per language).
- `menus_save_item` adds or updates an item. `kind` is "section" (a label without link), "page" (`page` is an id or path; the label defaults to the page title; `"children": true` adds its child pages, `depth` levels deep with at most `limit` pages per level) or "link" (`url` per language, optional `target` such as "_blank"). Use `parent` with an item id to nest.
- Items take `icon_before` and `icon_after` (icon CSS classes or names) and `languages` (codes the item shows in; default all).
- Menus leave out pages that are hidden, unpublished, below an unpublished page, or not available in the current language.
- Reorder, move and delete items with `pages_sort`, `pages_move` and `pages_delete`.

## Components

- A `component` field stores which app component a page shows and its settings. Settings are the same in every language, so use them for configuration only (a capacity, a layout); text visitors read comes from the component's translations or from translatable page fields.

## Permissions

You act with the roles of the user you work for. A tool refused for lack of a permission says which one (for example `[pages.publish]`) and, when limited, for which page or template: tell the user instead of retrying, and do not look for another tool that does the same thing.

## Page references

Wherever a tool asks for a page, give its id, its path in the default language or its name. A number is an id. A value with a slash is a path and nothing else: `/courses/risk/` looks only under `/courses/`. A bare name such as `risk` is the page named `risk` in the default language at any depth; when several pages share that name, tools refuse and ask for the path or id.

## Maintenance

`fields_clean_html` cleans stored values of html fields the way a save cleans them (scripts, event attributes and markup the editor does not produce are removed). It is destructive: dry-run it, show the counts per field, and ask before applying.

## Selectors

`template=course, parent=/courses/, title%=risk, price<300, sort=-title, limit=10`. Operators: `=`, `!=`, `<`, `>`, `<=`, `>=`, `%=` contains, `~=` contains every word in any order, `^=` starts with, `$=` ends with. `|` separates alternative values, and alternative fields: `title|summary%=risk`. `children.count>0` and `related.count=2` count child pages and values. Add `language=pt` to match the Portuguese values (with fallback). Pages of templates without a URL (slides, promos, settings, forms) are left out unless the selector names their template (`template=promo`), reads below such a page (`parent=` or `has_parent=` it), names them by `id=`, or adds `include=no_url` (or `include=all`).
