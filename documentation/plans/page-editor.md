# Plan: page editor

Status: ready to build. Decisions taken with the product owner on 2026-10-07 are marked **Decided**.

## Why

The page tree and the menu builder already link to `lynx.admin.pages.edit` and `lynx.admin.pages.create`, but those routes do not exist, so Edit and New stay hidden. Phase 11 asks for a ProcessWire style editor: per field language tabs (double click switches all), fallback previews, tab states, a Settings tab (names per language with URL preview, template, parent, status, redirects, backlinks, activity) and component field settings.

## Decisions

| Topic | Decision |
| --- | --- |
| Form technology | **Decided:** our own inputs per field type, written as Blade or Livewire components, styled only with the `--lynx-*` theme variables. |
| Writes | Proposed: through the existing actions only (`pages.save`, `pages.move`, `pages.switch_template`, `pages.status`, `pages.translate`), like every other admin screen (ADR-0019). |
| Saving | Proposed: explicit Save (and Cmd/Ctrl+S); no autosave. Drafts and versions stay a paid module (ADR-0018). |
| Conflicts | Optimistic locking with a page `revision` counter (see step 4). |
| Permissions | Proposed: built on the single gate first, then wired to the permissions plan (`pages.view`, `pages.edit`, `pages.publish` and so on) when that lands. |
| Layout | **Decided:** a full page editor at its own URL with a breadcrumb back to the tree, as in ProcessWire. |
| Rich text | **Decided:** Tiptap for html textareas, with our own toolbar styled by the `--lynx-*` variables. The admin build grows a small JavaScript bundle next to the CSS, built and committed like `dist/admin.css` and covered by the same CI check. |
| Agent chat panel | **Decided:** moved to phase 15, after every other feature works without it; it reuses the editor's Review changes diff to show the agent's plans before approval. |

## Screens

**Routes.** `GET /lynx/pages/{page}/edit` and `GET /lynx/pages/create?parent={id}&template={name}`. Registering them lights up Edit and New in the tree and the menu editor with no other change.

**Content tab.** One input per template field, in template order, using the field's label, description and `required` setting.

* Translatable fields get language tabs. Double clicking a tab switches every field to that language.
* Tab states: filled, empty, and "falls back to English" (the value a visitor would see comes from another language), shown on the tab itself.
* An empty translatable field shows its fallback value greyed out under the input, so editors see what visitors get today (ADR-0005: fallback at read time, never copied).
* Shared fields have no tabs.
* `showIf` and `requiredIf` field settings (ADR-0019) hide or require inputs as the form changes; the action enforces `requiredIf` again on save.

**Settings tab.**

* Names per language with a live URL preview built from the parent's paths; renaming shows that the old URL will redirect.
* Template (switching runs `pages.switch_template` as a dry run first and lists the fields whose values become orphans).
* Parent (page picker reusing the page tree, then `pages.move`).
* Status flags with the locked and system rules of ADR-0027; a locked page opens read only with an Unlock button.
* Redirects: the page's inactive paths per language (needs a small read API on `PathResolver`).
* Backlinks: pages that reference this page in page fields (needs a read API that queries the page reference field tables) and menus that link it.
* Activity: the page's entries from the activity log, with actor kind (user, MCP, AI).

**Header.** Breadcrumb from the tree, View link, Save, and a "Review changes" button that runs the save as a dry run and lists the `ChangeSet`, the same preview the agent's plans use.

## Field inputs

The admin package gets an input registry keyed by field type name:

```php
app(FieldInputs::class)->register('rating', 'my-module::inputs.rating');
```

| Field type | Input |
| --- | --- |
| text | Single line, with language tabs when translatable |
| textarea | Multi line; Tiptap when the field's `format` is html |
| integer | Number input with the field's min and max |
| toggle | Switch |
| page | Page picker with title search (single or multiple per the field's `multiple` setting, limited by `templates`) |
| component | Choice of registered component plus a form generated from its settings schema |
| anything else | JSON textarea, so module field types work before they ship an input |

An input is a Blade view that receives `$field`, `$code` (the language), `$model` (the `wire:model` path), `$placeholder` (the fallback value) and `$disabled`, and binds a value the action can validate. Modules register inputs for their own field types. Validation errors from `pages.save` (`values.price`, `names.pt`) are mapped back to the input and language tab that caused them.

## Core work needed

| Addition | Why |
| --- | --- |
| `pages.save` accepts `expected_revision` (done) | Optimistic locking |
| `PagesApi::redirects($page)` (done, on `PathResolver::history()`) | Old URLs list in Settings |
| `PagesApi::backlinks($page)` (done) | Backlinks list |
| `PagesApi::activity($page)` (done) | Activity list |
| `PathBuilder::preview($page, $names)` | URL preview while typing a name |
| Template family rules (done, ADR-0032) | New page offers only templates that fit under the parent (also enforced for MCP and the agent) |

## Build order (TDD throughout)

1. **Done.** The edit route (`/lynx/pages/{page}/edit`) and a read only editor: breadcrumb, template, View link, every template field with language tabs, tab states, fallback previews and double click to switch all fields, page references as links, and a locked banner with Unlock. Edit appears in the page tree. New waits for step 3, so it never leads to an empty screen.
2. **Done.** `FieldInputs` maps a field type to a Blade view (core: text, textarea, integer, toggle; modules register their own). The editor keeps the loaded values, sends only the fields and languages that changed to `pages.save`, empties become `null` so a translation falls back again, the fallback value is the placeholder of an empty translation, core errors show under their field, and locked pages render disabled. Page and component fields stay read only until step 5.
3. **Done.** `/lynx/pages/create?parent={id}` (New in the page tree): the template (preselected from the parent's children; `TemplateChoices` leaves out used singletons and templates modules exclude), the title and the template's other required fields, an optional URL name with a live preview using core's `PageName` rule, and "Publish now" (new pages start unpublished). The page is created through `pages.save` and opens in the editor.
4. **Done.** Pages carry a `revision` raised by every write to the page row (save, status, move, template switch; sorting among siblings does not count). `pages.save` takes an optional `expected_revision` and refuses a stale save with a `conflict` error naming who saved and when; the editor sends it, keeps the unsaved edits in the form and offers "Reload and discard my edits". A revision counter replaced the planned `updated_at` check because timestamps are stored to the second.
5. **Done.** Page references use `PagePicker`, a nested Livewire component bound with `wire:model`: the chosen pages with Remove buttons and a title search, honouring the field's `multiple` and `templates` settings. Component fields offer the components the field allows and a settings form generated from the component's settings schema (text, number, yes or no, choice lists); settings the form cannot edit say so, and core still validates them with the component's rules.
6. **Done.** Html textareas use Tiptap 3 (bold, italic, strikethrough, headings, lists, quote, link, undo and redo; the fallback text as placeholder). The admin bundles `resources/js/admin.js` with esbuild into `dist/admin.js`, served like the stylesheet and loaded before Livewire, with the bundled packages' licenses in `dist/admin.js.LICENSES.txt`; `npm run check` covers all of `dist/`. Core cleans html values on every save with `HtmlCleaner` (symfony/html-sanitizer), so the editor, the API, MCP and the agent store the same safe HTML. HTML stored before the sanitizer is cleaned the same way by `php artisan lynx:clean-html` (action `fields.clean_html`, `--dry-run` first).
7. Settings tab, in two parts.
   * **7a, done.** A Content and Settings tab (kept in the URL). Settings (`PageSettings`) edits URL names per language with a live URL preview and a note when the current URL will redirect, whether the page is published in each language, status flags, the template (a dry run first lists the fields whose values would be kept but hidden) and the parent (page picker, then `pages.move`). Each change tells the editor its new revision (`lynx-page-changed`), so unsaved content edits stay and the next content save does not report a false conflict; a template switch reloads the fields.
   * **7b, done.** Below the settings, `PageHistory` lists the page's old URLs per language (each marked as redirecting or taken by another page), the pages that reference it (menu items included; untitled pages read by template), and its activity with who did it (user names through the auth provider, MCP, AI agent, console) and a Show more. Core gained `PagesApi::redirects()`, `backlinks()` and `activity()`. `pages.save` now records the page change before the path changes, so creates and renames are filed under the page in the activity log; entries written before this fix stay filed under a path. Old URLs can be deleted (core action `redirects.delete`, with an inline confirmation; not on locked pages), and modules leave their pages out of "Linked from" with `BacklinkFilter` (the menus module excludes menu items).
   * **7c, done.** The unique status (ADR-0028): `pages.status` accepts `unique`, and `Pages\UniqueNames` keeps a unique page's default-language name free across the whole tree in `pages.save` and `pages.status`. Settings shows a Unique checkbox naming the page's name (not on the home page) and the error when another page holds the name. Page references now follow ProcessWire (ADR-0029): `get('alpha')` finds the first page named `alpha` at any depth, all digits is an id, a value with a slash is a path only, page actions refuse a bare name that several pages share, and a one-segment URL such as `/alpha/` redirects to the unique page with that name.
8. **Done.** Review changes runs `pages.save` as a dry run (errors show by their fields) and lists every changed field and language before and after (`ChangeReview` builds the entries from a page and a `pages.save` values input, so the agent panel can reuse it with `<x-lynx-admin::editor.review>`); any edit closes the review. Cmd/Ctrl+S saves where the Save button is offered. Leaving the page with unsaved edits asks first (`lynxEditorGuard` compares the form with the values it was loaded with, so edits not yet sent count too).
   The same information is a read tool, `get_page_history` (`Agents\PageHistory`), for MCP clients and the built-in agent; unlike the admin list it includes menu items, so an agent knows what links to a page before renaming or deleting it.
9. **Done.** Conditional fields (ADR-0030): `show_if` and `required_if` use ProcessWire's showIf syntax, `Templates\FieldCondition` evaluates them, templates refuse conditions that do not parse or name fields they lack, and `pages.save` enforces them (hidden fields are never required). The editor evaluates the same class on the server: fields a condition reads update the form as you type, hidden fields disappear and required markers follow `required_if`. The New page form asks for a `required_if` field once its condition makes it required.
10. **Done.** Permissions wiring (ADR-0033, permissions plan step 4b): the editor opens with `pages.view`, is read only without `pages.edit`, offers Unlock with `pages.status`, and the Settings tab shows only what the user may change.

The agent chat panel, planned here as step 9, moved to phase 15 of the implementation plan: every feature must work without it first.

Each step is a branch off `main` and its own PR, so none depends on an unmerged one.

## Risks

* **Large templates.** A page with many translatable fields and languages is a big Livewire state. Inputs keep only the active language in the DOM and load others on tab switch if measurements show a problem.
* **Rich text.** The editor choice affects sanitising: HTML from the editor must pass the same rules as HTML from MCP and imports, so sanitising belongs in the field type's `normalize()`, not in the input.
* **showIf and requiredIf.** Evaluated in the browser for display and again in the action for safety; the two must share one implementation of the condition rules.
