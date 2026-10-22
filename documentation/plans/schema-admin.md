# Plan: templates and fields in the admin

Status: built (steps 1 to 5). Decisions taken with the product owner on 2026-10-08 are marked **Decided**.

## Why

ProcessWire's Setup > Templates and Setup > Fields let a site builder shape the site without code. Lynx can already do all of it through actions (`fields.create`, `fields.update`, `fields.delete`, `templates.create`, `templates.update`, `templates.delete`), MCP, the agent and schema YAML (`lynx:schema:export` and `apply`), but the admin has no screen for it. Phase 11 now includes these screens.

## Screens

**Fields** (`/lynx/fields`)

* A list with name, label, type, translatable, and the templates that use each field; filter by type and by template, search by name or label.
* New field: name, label, type, translatable, and a settings form generated from the type's `settingsSchema()` (the same generator the page editor uses for component settings). The name is checked live against the rules of `fields.create`.
* Edit field: label, description, settings and text formatters (`documentation/plans/text-formatters.md`); the type's settings form again. Name, type and translatable are fixed after creation. A side panel lists the templates using the field, each linking to its template.
* Delete: a dry run first, showing the templates it is removed from and the number of stored values that would be dropped; then an inline confirmation naming the field.

**Templates** (`/lynx/templates`)

* A list with name, label, number of fields, number of pages, and flags (has URL, singleton).
* New template: name, label, and optionally "copy fields from" another template (`from`).
* Edit template, in tabs:
  * **Fields:** the ordered field list, sortable with `wire:sort` like the page tree. Add an existing field (or create one inline and add it), remove a field. Each field row opens its overrides for this template: label, description, required, `show_if`, `required_if` (the pivot settings `templates.update` already accepts).
  * **Family:** has URL, singleton, whether pages may have children, the templates allowed for children and for the parent (ADR-0032), and the templates pages may switch to (`allowed_templates`).
  * **Pages:** how many pages use the template, with a link to find them.
* Removing a field from a template keeps the stored values (they become orphans, ADR-0012) and says so; purging them stays an explicit action (`pages.purge_orphans`).
* Delete: refused while pages use the template (the action already refuses), with the page count and a link.

## Rules

* Every write goes through the existing actions, so the admin, MCP, the agent and YAML share one set of rules. Each save runs a dry run first when it is destructive, and the change list is shown with the same component as the page editor's Review changes.
* Condition fields (`show_if`, `required_if`) get an input that validates the syntax as you type, with the fields of the template offered as completions (ADR-0030).
* System templates and fields that modules own (the menu templates and `menu_*` fields) show as read only, with the module named.

## Decisions

1. **Decided:** a config flag `lynx-admin.schema_editing`, on by default. Teams that change the schema through YAML in Git set it off in production; the screens become read only there, with a note to use `lynx:schema:apply`.
2. **Decided:** a field's type and translatable flag are fixed after creation in v1. To change them, create a new field; copying values across can come later as its own action.
3. **Decided:** labels only in v1. Field and template names are fixed, because code reads `$page->field_name`; renaming can come later as its own action with a warning.
4. Proposed: both screens need `schema.manage` (already in the permissions plan).

## Build order (TDD throughout)

1. **Done.** Fields list (`/lynx/fields`, filters by type, template and text kept in the URL) and the field form (`/lynx/fields/create`, `/lynx/fields/{name}/edit`): label, type, translatable with its fallback, description, and the settings form generated from the type's settings schema (`SchemaForm`, shared with component settings; lists as comma separated text). On the way, core now reports type settings errors as `settings.<name>`, `fields.update` removes a setting given as null, and the action runner no longer drops nested input that only some rules mention (a field created with a description lost its other settings).
2. **Done.** Field delete: a dry run lists the stored values and the templates the field is removed from (`fields.delete` now names them, for agents too), then an inline confirmation naming the field.
3. **Done.** Templates list (`/lynx/templates`: fields, pages, flags), New template with "copy fields from", the label, and the Fields section: drag to reorder (`wire:sort`), add an existing field, remove one (values kept as orphans, said on screen), and per template overrides (label, description, required, show if, required if), saved in one `templates.update` whose errors show above the list.
4. **Done.** Family section and template delete. Core gained the family rules this needs (ADR-0032): `allow_children`, `child_templates` and `parent_templates`, enforced by `pages.save` for new pages, `pages.move` and `pages.switch_template`; the New page screen and the template switch only offer templates the rules allow. Delete runs a dry run first and is refused while pages use the template.
5. **Done.** `SchemaOwners`: modules register the templates and fields they own (the menus module registers its templates and `menu_*` fields), shown read only with the module named and a badge in the fields list. `lynx-admin.schema_editing` (env `LYNX_ADMIN_SCHEMA_EDITING`, on by default) makes every schema screen read only and points to `lynx:schema:apply`. Read only screens refuse writes on the server too (403), whatever the browser sends.

Each step is a branch off `main` and its own PR.
