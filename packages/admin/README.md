# lynxcms/admin

> [!NOTE]
> This is a read-only split of [`packages/admin`](https://github.com/LynxCMS/lynxcms/tree/main/packages/admin) in the [Lynx CMS monorepo](https://github.com/LynxCMS/lynxcms). Open issues and pull requests there. Lynx is not ready for production yet: see its README.

Livewire admin for Lynx CMS. It calls the same API and actions as every other caller and never touches the database directly. The first screen is the page tree at `/lynx/pages`.

## Page tree

`<livewire:lynx-admin.page-tree />` renders a ProcessWire-style tree. Click a title to open its children and show its actions. **Move** starts move mode: drag the highlighted row by its handle, or use the arrow keys (up and down reorder, right indents under the previous sibling, left outdents). Enter or Esc ends move mode. To move a page to another pager page, drop it on that page's number (or arrow): it lands at the top of that page. Keyboard moves past the edge of a pager page follow the row onto the next or previous page.

Open branches and pager pages are kept in the URL (`?open[]=…&page[parent]=n`), so a reload or a shared link shows the same tree. Because of this, use one page tree per screen.

Every write goes through the pages API (`pages.sort`, `pages.move`, `pages.status`, `pages.delete`), so the same validation, hooks, activity log and events apply as for MCP and the AI agent.

Properties (all fixed after mount):

| Property | Default | Purpose |
| --- | --- | --- |
| `root` | `/` | Id or path of the page the tree starts from. The menu builder passes its menu page. |
| `sortable` | `true` | Set to `false` for a read-only tree. |
| `perPage` | `lynx-admin.tree.per_page` | Children shown before the numbered pager appears (default 20). |

**Edit** opens the page editor at `/lynx/pages/{page}/edit`. It shows every field of the page's template with its value per language: a dot on each language tab marks filled values, a dashed dot marks values that fall back to another language (shown greyed below the tab), and double clicking a tab switches every field to that language. Text, textarea, integer, toggle, page reference and component fields are editable: Save sends only what changed to `pages.save`, emptying a translation makes it fall back again, and errors show under their field. Page references use a picker with title search (`<livewire:lynx-admin.page-picker wire:model="…" />`, reusable in other screens); component fields show a settings form generated from the component's settings schema.

**Review changes** lists what Save would change, field by field and language by language, after checking it with a dry run; **Cmd/Ctrl+S** saves, and leaving the page with unsaved edits asks first. Other screens reuse the list with `app(ChangeReview::class)->forSave($page, $values)` and `<x-lynx-admin::editor.review :entries="$entries">` (actions go in the slot).

The **Settings** tab edits the page's URL names per language (with a URL preview; old URLs redirect), whether it is published in each language, its status (unpublished, hidden, locked, unique), its template (a preview lists fields whose values would be kept but hidden) and its parent. Below that it lists the page's old URLs (each can be deleted so it stops redirecting), the pages that link to it, and its activity. Modules leave pages that link through their own screens out of that list with `app(BacklinkFilter::class)->exclude('my-template')`; the menus module does this for menu items.

Modules add inputs for their own field types with a Blade view, a `FormConverter` and an optional read-only display view: `app(FieldInputs::class)->register('rating', 'my-module::inputs.rating', RatingConverter::class, 'my-module::values.rating')`. The view receives `$field` (with the field's `settings`), `$code`, `$model` (bind it with `wire:model="{{ $model }}"`), `$value` (the current form value), `$placeholder`, `$disabled` and `$live`. **New** opens `/lynx/pages/create?parent={id}`: choose a template, give the title (and any other required field), and the page is created unpublished and opens in the editor. The URL name comes from the title unless you type one. Modules hide templates that are created elsewhere with `app(TemplateChoices::class)->exclude('my-template')`.

Every status is shown (hidden pages faded, unpublished struck through), so positions always match the real sibling order.

## Access

Admin routes use `lynx-admin.middleware` (default `web` and `auth`) plus the gate `accessLynxAdmin`, which the page tree also checks on every Livewire request. By default any signed-in user passes. Restrict it in your app:

```php
Gate::define('accessLynxAdmin', fn (User $user): bool => $user->is_editor);
```

## Configuration

Publish with `php artisan vendor:publish --tag=lynx-admin-config`. Keys: `prefix`, `middleware` (keep `auth` in production), `theme` (`light`, `dark` or `system`), `appearance`, `stylesheets`, `schema_editing` and `tree.per_page`.

## Components and theming

The admin is built on [BlatUI](https://blatui.remix-it.com) (ADR-0034). Its components live in `resources/views/components/ui` and are used as `<x-lynx-admin::ui.button>`, `<x-lynx-admin::ui.card>` and so on. Modules adding admin screens should use them too. The admin ships a prebuilt stylesheet and script, so host apps need no build step.

### Presets

Every option of BlatUI's customizer is a key of `lynx-admin.appearance` and an environment variable, applied without a rebuild. Values BlatUI does not know are ignored.

| Key | Environment variable | Values |
| --- | --- | --- |
| `base` | `LYNX_ADMIN_BASE` | neutral, stone (default), zinc, slate, gray, mauve, olive, mist, taupe |
| `theme` | `LYNX_ADMIN_ACCENT` | default, amber, blue, cyan (default), emerald, fuchsia, green, indigo, lime, orange, pink, purple, red, rose, sky, teal, violet, yellow |
| `radius` | `LYNX_ADMIN_RADIUS` | 0, 0.3 (default), 0.5, 0.625, 0.75, 1 |
| `font` | `LYNX_ADMIN_FONT` | sans (Instrument Sans), system, serif, mono, inter (default), geist, manrope, jakarta, space-grotesk, dm-sans, outfit, sora, lora, source-serif |
| `heading_font` | `LYNX_ADMIN_HEADING_FONT` | the same (default mono); sans follows the body font |
| `shadow` | `LYNX_ADMIN_SHADOW` | none (default), sm, default, lg, xl |
| `spacing` | `LYNX_ADMIN_SPACING` | compact, default (default), comfortable |
| `tracking` | `LYNX_ADMIN_TRACKING` | tight, normal, wide (default) |
| `input_style` | `LYNX_ADMIN_INPUT_STYLE` | outline (default), fill, inset |
| `load_fonts` | `LYNX_ADMIN_LOAD_FONTS` | true (default) or false |

The colour scheme (light, dark or system) is `lynx-admin.theme` (`LYNX_ADMIN_THEME`). Web fonts load from fonts.bunny.net, a GDPR friendly Google Fonts mirror. Set `load_fonts` to false to serve them yourself (add their stylesheet to `lynx-admin.stylesheets`) or to make no outside requests; system fonts show until yours load.

### Your own theme

1. Run `php artisan vendor:publish --tag=lynx-admin-theme` (copies `public/vendor/lynx-admin/theme.css`).
2. Add `'/vendor/lynx-admin/theme.css'` to `lynx-admin.stylesheets`.
3. Set BlatUI tokens (`--primary`, `--background`, `--muted`, `--radius`…, dark values under `.dark`), or paste a theme from BlatUI's customizer.

The page tree has a few tokens of its own, each defaulting to a BlatUI token:

| Variable | Used for |
| --- | --- |
| `--lynx-tree-indent`, `--lynx-tree-row-py`, `--lynx-tree-row-px` | Tree indentation and row padding |
| `--lynx-title-size`, `--lynx-title-weight`, `--lynx-title-color` | Page titles |
| `--lynx-row-hover`, `--lynx-highlight` | Row hover, the selected title and the row being moved |
| `--lynx-dropzone` | Drop target under the cursor while dragging |
| `--lynx-hidden-opacity` | Hidden pages |
| `--lynx-notice-success`, `--lynx-notice-warning`, `--lynx-notice-error` (and each `-foreground`) | The notification bar: saved, nothing to save, refused |

These variables and `lynx-admin.appearance` are a public contract and follow semantic versioning like `@api` classes. A test fails if the admin reads a `--lynx-*` variable that `theme.stub.css` does not document, and another fails if a view uses a raw palette class such as `bg-gray-100`.

## Development

```bash
cd packages/admin
npm ci
npm run build   # writes dist/admin.css, dist/admin.js and dist/admin.js.LICENSES.txt (all committed)
npm run check   # rebuilds and fails if dist/ differs from the committed files (CI runs it)
npm run watch   # CSS only
```

`dist/admin.js` holds the rich text editor (Tiptap, from `resources/js/admin.js`), bundled with esbuild. Html textarea fields (`format: html`) use it; their HTML is cleaned by core on every save.

## Fields

**Fields** (`/lynx/fields`) lists every field with its type and the templates that use it, filtered by type, template or text. **New field** asks for the name, label, type, whether it is translatable (and what to show when a translation is empty), a description for editors, and the settings of the chosen type, generated from the type's settings schema. Editing changes the label, description and settings; the name, type and translatable flag are fixed once the field exists. Text fields can attach **formatters** (smart quotes, paragraphs, Markdown, external links, and any a module registers), run in the listed order when a page is shown (ADR-0031); the page editor shows a preview of the formatted value under such fields.

## Templates

**Templates** (`/lynx/templates`) lists every template with its number of fields and pages. **New template** asks for the name, label and, optionally, a template to copy fields from. Editing a template orders its fields by dragging, adds and removes fields (a removed field's values stay on the pages until purged), and sets overrides for this template only: label, description, required, show if and required if (conditions such as `format=onsite|hybrid`, ADR-0030). **Save fields** saves the whole list at once. The **Family** section sets whether pages have a URL, whether only one page may use the template, whether its pages may have children, the templates allowed for children and for the parent (ADR-0032), and the templates pages may switch to. The New page screen only offers templates the parent allows. A template no page uses can be deleted, after a summary of what goes.

Modules mark the templates and fields they depend on with `app(SchemaOwners::class)->register('My module', templates: [...], fields: [...])`; the screens show them read only. Sites whose schema comes from YAML in Git set `LYNX_ADMIN_SCHEMA_EDITING=false` (config `lynx-admin.schema_editing`) where it should not change, typically production: the Templates and Fields screens become read only there and point to `php artisan lynx:schema:apply`.

## Who may use the admin

The `accessLynxAdmin` gate decides who may open the admin. By default it allows users holding the `admin.access` permission, which every signed in user has until someone is given a role (ADR-0033). Lynx permissions are Laravel abilities named `lynx.<permission>`, so templates can use `@can('lynx.pages.edit', $page)`.

**Roles** (`/lynx/roles`) and **Users** (`/lynx/users`) are for superusers. A role grants permissions, page permissions optionally limited to templates and to branches of the page tree; the Users screen lists the app's users (through `LynxUsers`; bind your own implementation if users do not live in the `users` provider's Eloquent model) and sets their roles. The last superuser cannot lose the role.

