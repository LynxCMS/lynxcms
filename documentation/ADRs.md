# Architecture Decision Records

Each record follows Michael Nygard's template: Status, Context, Decision, Consequences. Records are never edited after acceptance; a change of mind is a new record that supersedes the old one. All records below were written on 2026-10-06 from the design discussion behind the plan.

| ADR | Decision | Status |
| --- | --- | --- |
| 0001 | Record architecture decisions | Accepted |
| 0002 | Build the new core as Lynx 2.0 | Superseded |
| 0003 | Store translations as rows, not columns | Accepted |
| 0004 | One typed table per field, created at runtime | Accepted |
| 0005 | Read-time, per-field fallback with per-language chains | Accepted |
| 0006 | An always-present default language, stored as a setting | Accepted |
| 0007 | Resolve the language from URL, session, user, then default | Accepted |
| 0008 | Pages for everything, including settings and reusable content | Accepted |
| 0009 | All writes are actions run through an ActionRunner | Accepted |
| 0010 | ProcessWire-style selectors on the Query Builder; Eloquent only for system tables | Accepted |
| 0011 | Paths per language with an active flag instead of path history | Accepted |
| 0012 | Template switching keeps orphaned values | Accepted |
| 0013 | PostgreSQL first, SQLite supported | Accepted |
| 0014 | Schema sync through YAML export and apply, keyed by uuid | Accepted |
| 0015 | AI-first: an official MCP server generated from the actions | Accepted |
| 0016 | Record the origin of every translated value | Accepted |
| 0017 | Engine-neutral templates: Blade by default, Twig and Latte as plugins | Proposed |
| 0018 | Open core: MIT core, paid modules on public extension points | Proposed |
| 0019 | Livewire admin as a separate package; data hooks in core, UI hooks in admin | Accepted |

Added later: ADR-0022, start fresh as lynxcms/\* (Accepted); ADR-0023, keep read-time effective-value queries after the spike (Accepted); ADR-0021, app Livewire components on CMS pages (Accepted); ADR-0020, a built-in AI agent on laravel/ai from the start. Status: Accepted

## ADR-0001: Record architecture decisions

**Status:** Accepted

**Context:** The design was shaped over a long discussion comparing ProcessWire, Sitecore, Statamic, WordPress, Craft 6 and Lynx 1. Without a record, the reasons behind choices will be lost, and contributors to an open-source project will reopen settled questions.

**Decision:** Keep architecture decisions as Nygard-style records (Status, Context, Decision, Consequences) in this tab. Accepted records are not rewritten; a change is a new record that marks the old one Superseded.

**Consequences:** Every significant change needs a short record. Contributors can see why the design is the way it is before proposing changes.

## ADR-0002: Build the new core as Lynx 2.0

**Status:** Superseded by ADR-0022

**Context:** Lynx 1 is an MIT Laravel package with JSON content per locale, languages in config and a single fallback locale. Around that core it has tested, documented features: slug and path history, locale middlewares, scheduling, locking, revisions, a recycle bin, webhooks, an audit log, export and import, a 12-tool MCP server, Boost skills, PHPStan level 8 and a security audit. Its core cannot add languages at runtime or fall back per field through a chain.

**Decision:** Release the new design as the next major version of Lynx under the same package name and license. Replace the core with the one in the plan, and port Lynx 1's surrounding features module by module.

**Consequences:** Existing documentation, tests, middleware and MCP design are a head start rather than throwaway work. Lynx 1 users need a migration path from JSON-per-locale storage. The name and Packagist history carry over.

## ADR-0003: Store translations as rows, not columns

**Status:** Accepted

**Context:** ProcessWire stores each language as a column (`data`, `data1012`, …), so adding a language runs `ALTER TABLE` on every multilingual field table. On large sites that locks tables and needs a backfill. Adding languages in production without migrations is the most important requirement.

**Decision:** Translatable field tables use `(page_id, language_id)` as their key, with one row per language. Languages are rows in a `languages` table.

**Consequences:** Adding a language is a single `INSERT`. Reading a value with fallback needs the rows for the whole language chain, and filtering or sorting on the value a visitor sees needs `COALESCE` across joins (see ADR-0005 and the performance spike).

## ADR-0004: One typed table per field, created at runtime

**Status:** Accepted

**Context:** Users create custom fields without migrations. The alternatives are a JSON column per page and language (Lynx 1, Craft 6) or a key-value table (WordPress `postmeta`). JSON avoids DDL entirely but makes filtering and sorting depend on JSON extraction and expression indexes; key-value tables lose types.

**Decision:** Each field gets its own `lynx_f_{name}` table with a typed `data` column, created by `CreateField` through `FieldSchemaManager`. Shared fields have no `language_id` column.

**Consequences:** Selectors compile to ordinary indexed queries. Creating a field runs DDL at runtime, which is safe because it only creates new tables, and is transactional on PostgreSQL. A field cannot appear twice in one template, and JSON storage remains an option for display-only fields later.

## ADR-0005: Read-time, per-field fallback with per-language chains

**Status:** Accepted

**Context:** Pages are often partly translated. Craft copies values to other sites when content is saved, which makes adding a language or changing a field's translation method trigger large resave jobs. Lynx 1 and ProcessWire fall back to one default language only. Sitecore resolves fallback at read time, per field, through a chain set on each language.

**Decision:** Each language has an optional `fallback_id`, building a chain that ends at the default language. A missing row falls back through the chain; an explicit empty row shows nothing. Each field can opt out with `fallback = none`. Values are returned as `TranslatableValue` objects that know which language they came from.

**Consequences:** Adding or changing a language costs nothing at write time. Templates can mark fallback content with the right `lang` attribute. Queries on the visible value are more expensive than strict queries and may need a materialised index.

## ADR-0006: An always-present default language, stored as a setting

**Status:** Accepted

**Context:** ProcessWire always has a default language, created at install with an empty name and the label Default, which editors rename later. In ProcessWire it is tied to the base `data` column, so it cannot be switched. Sites sometimes need to change their main language after launch.

**Decision:** `cms:install` creates a language with code `default`, name Default, an empty URL prefix and no locale. It can be renamed and given a prefix and locale but never deleted. Which language is the default is stored in `settings.default_language_id`, and `languages.locale` is separate from the code because `default` is not a valid locale. Fallback chains and URL prefixes are explicit and never derived from "is default".

**Consequences:** Switching the default later is a setting change plus a pre-check and queued jobs (redirects, search index, outdated-translation baseline), with no data moved. Required-field validation applies to the default language only.

## ADR-0007: Resolve the language from URL, session, user, then default

**Status:** Accepted

**Context:** Front-end pages carry the language in the URL, but the admin, form posts and AJAX calls do not. Users need to switch language and have it remembered. Lynx 1 already solved this with three composable middlewares.

**Decision:** Adopt Lynx 1's middlewares: `DetectLocale` (required, URL prefix, runs before `SubstituteBindings`), `RememberLocale` (session and cookie, GET only, optional `Accept-Language`) and `RedirectDefaultLocale` (301 when the default has no prefix). Without a prefix the order is session, then the user's `language_id`, then the default. `SetCurrentLanguage` also calls `App::setLocale()`. The chain sits behind a swappable `LocaleResolver`.

**Consequences:** URLs always win on the front end, so links and SEO are unambiguous. Laravel's own `__()` strings follow the visitor's language. Subdomain or domain-based detection is a custom resolver, not a core change.

## ADR-0008: Pages for everything, including settings and reusable content

**Status:** Accepted

**Context:** Global settings, global SEO, testimonials and menus must be translatable too. A separate storage system for them would need its own multilingual support, editor, API and sync.

**Decision:** Model them as pages, as ProcessWire does. Templates get two flags in `settings`: `has_url: false` (no paths, never served) and `singleton: true` (only one page may use the template). They usually live under a hidden `_system/` branch, and `$cms->settings('name')` loads a singleton once per request, cached per language. Developer-owned interface strings stay in Laravel's `lang/*.json`.

**Consequences:** Settings get fallback, language tabs, machine translation, schema sync and selectors with no extra code. The page tree contains non-public branches that listings must exclude.

## ADR-0009: All writes are actions run through an ActionRunner

**Status:** Accepted

**Context:** The CMS must be fully usable without a UI, and later from a Livewire admin, Artisan, imports and AI agents. Laravel events only cover "after something happened", while modules also need to change or stop a write, as ProcessWire hooks allow.

**Decision:** Every write is a `final` action class with a single `handle()` method. Actions run through an `ActionRunner` that opens a transaction, runs `before` hooks, calls `handle()`, runs `after` hooks and fires domain events after commit. Every action supports `dryRun: true`, and an `after` hook writes the activity log. Validation lives inside actions.

**Consequences:** All callers share one code path and one set of rules. Hooks, dry runs, audit and MCP tools come for free with each new action. Adding the runner later would break modules, so it is part of the MVP.

## ADR-0010: ProcessWire-style selectors on the Query Builder; Eloquent only for system tables

**Status:** Accepted

**Context:** Pages have fields that are defined at runtime and stored in many tables, which Eloquent's fixed-table models do not fit. ProcessWire's selector strings are concise and well liked. Craft 6's element query works but is about 1,400 lines with some 20 traits.

**Decision:** A small selector engine (lexer, parser, AST, compiler) compiles to Laravel's Query Builder. Each field type compiles its own filter and sort. Shared fields use `EXISTS`; translatable fields use joins over the language chain with `COALESCE`. Eloquent is used only for languages, fields and templates; `Page` is a domain object hydrated by `PageLoader`.

**Consequences:** Field types can add query behaviour without the core knowing them. The selector engine is ours to maintain and document. Selector strings also serve as the stored format for conditions in the admin.

## ADR-0011: Paths per language with an active flag instead of path history

**Status:** Accepted

**Context:** Slugs are translated (`/courses/x/` and `/pt/cursos/x/`), each path is built from every ancestor's name in that language, and renames or moves must not break old links. ProcessWire uses a separate PagePathHistory module. Lynx 1 keeps old paths in the same table with an `active` flag and a partial unique index.

**Decision:** `page_paths(page_id, language_id, path, active)` with one active path per page and language, enforced by a partial unique index, plus an index on `lower(path), language_id`. `RebuildPaths` marks the previous row inactive instead of deleting it. The redirect middleware answers an inactive path with a 301 to the active one in the same language. If an ancestor has no name in a language, its default-language name is used.

**Consequences:** Path history and redirects need no extra table. Routing is one indexed lookup. Renaming a page with many descendants writes a row per descendant per language, done in a queued job for large subtrees.

## ADR-0012: Template switching keeps orphaned values

**Status:** Accepted

**Context:** Pages change templates as sites evolve. ProcessWire deletes values of fields that the new template lacks, after a warning. Lynx 1 never deletes them automatically and offers an explicit purge.

**Decision:** `SwitchTemplate` checks the template's `allowed_templates`, and its dry run lists fields that would become orphaned. Their rows stay in the field tables. Removing a field from a template behaves the same way. `PurgeOrphanedValues` is the only action that deletes field data.

**Consequences:** No data is lost by mistake, and switching back restores the values. Orphaned rows use space until purged, so the admin should show them and offer the purge.

## ADR-0013: PostgreSQL first, SQLite supported

**Status:** Accepted

**Context:** Runtime DDL is involved in field creation. PostgreSQL runs DDL inside transactions, has partial and expression indexes and JSONB. SQLite is convenient for small sites and local development, and also supports transactional DDL and partial indexes, but allows one writer at a time and only ASCII case-insensitive `LIKE`.

**Decision:** Target PostgreSQL first, with SQLite as a second supported database. Field types use only portable Blueprint column types, and database-specific SQL lives in the selector compiler's per-driver parts. CI runs the full suite on both.

**Consequences:** `CreateField` rolls back completely on failure. Some features (full-text search) need per-driver implementations. Open question for the open-source release: much of the PHP audience runs MySQL or MariaDB, and supporting it would mean adding a third CI target and handling non-transactional DDL.

## ADR-0014: Schema sync through YAML export and apply, keyed by uuid

**Status:** Accepted

**Context:** Languages, fields and templates live in the database and can change in production through the admin or AI agents. Developers need those changes locally, and auto-increment ids differ between machines. Craft solves this with project config; Lynx 1 defines templates as PHP classes synced with `lynx:sync`.

**Decision:** Languages, fields and templates carry a `uuid`. `cms:schema:export` writes one YAML file each into `cms/schema/`, committed to git. `cms:schema:apply` diffs the files against the database and applies the changes through the normal actions; destructive changes need `--force`, and conflicts stop the run. PHP template classes stay as an option, compiled by `cms:sync` into the same actions. Once a site is live, the recommended setup locks schema editing in production.

**Consequences:** One path changes the schema, whether from code, YAML, the admin or AI. Page content is not synced this way. The MVP only adds the `uuid` columns.

## ADR-0015: AI-first: an official MCP server generated from the actions

**Status:** Accepted

**Context:** A main reason for this CMS to exist is that developers and non-developers can build and run sites through AI agents. WordPress now has an official MCP adapter, and Statamic and Craft have community MCP servers, so AI access alone is not a differentiator. Being safe for non-developers is.

**Decision:** Ship an official `mcp` package on `laravel/mcp` that generates one tool per registered action, plus read tools and `describe_schema`. Every write tool supports dry runs. Calls are recorded in the activity log with `actor_kind = mcp`, and an allowlist limits which languages an agent can write. A `ContentAssistant` prompt and a usage-guide resource explain the CMS to agents, as in Lynx 1.

**Consequences:** Every new action becomes available to agents automatically, with the same validation as every other caller. Schema changes take effect without deploys, so agents can build whole sites. Permissions per agent and reversible schema changes are needed before non-developers use it on live sites.

## ADR-0016: Record the origin of every translated value

**Status:** Accepted

**Context:** Machine translation (DeepL or an LLM) and content generation are planned as modules. Without knowing where a value came from, they could overwrite human work, editors cannot see what needs review, and outdated translations go unnoticed.

**Decision:** Add `value_meta(page_id, field_id, language_id, origin, source_hash, provider)` in the MVP, with origin `human`, `machine` or `copied`. `SavePage` accepts an `origin` option that defaults to `human`. Languages get `provider_codes` to map codes such as `pt` to `PT-PT`. Field tables stay unchanged.

**Consequences:** Translation modules can fill only missing values, never overwrite human text, flag outdated translations by hash, and show review states in the admin. Every save writes one extra row per changed value.

## ADR-0017: Engine-neutral templates: Blade by default, Twig and Latte as plugins

**Status:** Proposed

**Context:** Some teams prefer Twig or Latte. Blade compiles to PHP and cannot be sandboxed, while Twig and Latte both have sandbox modes, which matter if AI agents or non-developers edit templates. Craft 6 renders both Twig and Blade.

**Decision:** The core renders through Laravel's view system and stays engine-neutral: helpers are thin wrappers around services (`HreflangBuilder`, `SeoResolver`), values are `TranslatableValue` and `HtmlValue` objects, field types declare `isHtml()`, and `Page` implements `__isset()`. Twig and Latte arrive as plugins that register an engine, mark `HtmlValue` as safe, and expose the helpers. The admin stays Livewire and Blade.

**Consequences:** No engine is a core dependency. A contract test renders the same page through each registered engine. A sandboxed engine could be a paid module aimed at AI-edited templates.

## ADR-0018: Open core: MIT core, paid modules on public extension points

**Status:** Proposed

**Context:** The plan is to open-source the CMS and sell advanced modules, as ProcessWire does with its Pro modules. Once others build on the code, every internal class they touch becomes a promise.

**Decision:** Split the repository into `contracts`, `core`, `mcp` and `admin` packages. Mark classes `@api` or `@internal`, and version only `@api` semantically. Modules are Composer packages with a `CmsModule` service provider and capability traits, following Craft 6's plugin design, and extend the system through action hooks, domain events and the value-resolution pipeline. Multilingual support stays complete in the free core. Paid modules (AI translation, static cache, page builder, versions and workflows, advanced SEO, search, multi-site, forms) ship through a private Composer repository with license keys and use only `@api` code.

**Consequences:** If a paid module needs a private shortcut, the core gains an extension point instead. License terms need a legal review before launch. Revenue depends on updates, support and compatibility rather than code obfuscation.

## ADR-0019: Livewire admin as a separate package; data hooks in core, UI hooks in admin

**Status:** Accepted

**Context:** The admin should match ProcessWire's editing experience: per-field language tabs, shared fields without tabs, a Settings tab with per-language names. Filament offers field hooks such as `afterStateUpdated` and `mutateFormDataBeforeSave`, but anything that changes data must also apply to API, import and AI writes.

**Decision:** The admin is an optional Livewire package that calls the same API and actions and never touches the database directly. Data hooks live in the core: `FieldType::normalize()`, per-field save hooks through the `ActionRunner`, and declarative `showIf`/`requiredIf` conditions stored in field settings. Interface hooks (slug suggestions, display formatting, live previews) live in the admin. Building the forms on Filament's Forms package remains an option, kept out of the core.

**Consequences:** The CMS runs headless without the admin. The same rules apply whatever the caller. Lynx 1's admin PRD and user stories carry over as the starting brief.

## ADR-0020: A built-in AI agent on laravel/ai from the start

**Status:** Accepted

**Context:** MCP (ADR-0015) serves external agents such as Claude or an IDE. Users also want to ask the CMS directly, from Artisan, PHP or the admin, for example "Create a new course from this Markdown file and translate it to PT" or "Create a consulting template from the course fields and add x, y and z". Laravel's official AI SDK (laravel/ai) provides agents, tools, structured output, file attachments, conversation memory, queueing, streaming and test fakes across providers such as Anthropic, OpenAI and Gemini.

**Decision:** Ship an `ai` package in the MVP built on laravel/ai. Actions declare their input with Laravel's `JsonSchema` contract, shared by laravel/mcp and laravel/ai, so one registry produces both MCP tools and `ActionTool` agent tools. `CmsAgent` implements `Agent`, `HasTools` and `Conversational`, with read tools (`describe_schema`, `describe_field_types`, `get_template`, `find_pages`, `get_page`). It runs in plan mode by default: writes are dry runs collected as a changeset, and approval replays the recorded action calls without asking the model again. Markdown conversion and translation are deterministic actions (`TranslatePage` with an LLM `TranslationProvider`), not free text from the agent. Entry points: `$cms->ai()->ask()`, `cms:ask`, and later a streaming chat panel in the admin.

**Consequences:** Every new action is immediately usable by the built-in agent and by MCP clients. The CMS gains a dependency on laravel/ai and provider API keys for AI features, which stay optional. Each field type must expose a settings schema so the agent can turn a written spec into valid fields. Tests use `Ai::fake()` and assert which tools were used.

## ADR-0021: App Livewire components on CMS pages

**Status:** Accepted

**Context:** Sites need interactive parts, such as an enrolment or contact form, built as Livewire components in the app with their own models and tables, while the rest of the page comes from the CMS. Editors sometimes need to choose which pages show a component and configure it. Livewire's update requests go to `/livewire/update`, which carries no language prefix.

**Decision:** Templates are Blade views, so app components can be embedded directly. For editor-chosen components, add a component registry (`Cms::components()->register()` with a settings schema), a `component` field type storing alias and settings, and a `@cmsComponent` directive rendering through `<livewire:dynamic-component>`. An optional Livewire integration provides `UsesCmsPage`, which passes the page as an id and restores the language on every Livewire request. Component data stays in the app's tables, not in CMS content.

**Consequences:** The core does not depend on Livewire. Text shown by components stays translatable through ordinary page fields or Laravel's `lang` files. Pages with components are excluded from the static cache unless lazy-loaded. The AI agent can add components to templates using the registered settings schema.

## ADR-0022: Start fresh as lynxcms/\*, not as Lynx 2.0

**Status:** Accepted (supersedes ADR-0002)

**Context:** ADR-0002 proposed shipping the new core as the next major version of Lynx to keep Lynx 1's package name and history. Lynx 1 was never released publicly, so there are no users to migrate and no compatibility to keep.

**Decision:** Build in a fresh repository (lynx-evolved) as a monorepo with packages `lynxcms/contracts`, `lynxcms/core`, `lynxcms/mcp`, `lynxcms/ai` and `lynxcms/admin` under the `LynxCms` namespace. The API root is `lynx()` and the `Lynx` facade, commands are `lynx:*`, and tables are prefixed `lynx_`. The first public release is 1.0. Lynx 1's ideas (path history with an active flag, locale middlewares, template switching that keeps data, MCP prompt and resource, quality tooling) are carried over as code and documentation, not as a compatibility layer.

**Consequences:** No migration command from Lynx 1's JSON storage is needed, and phase 12 ports features rather than data. The `lynxcms` vendor name must be registered on Packagist before release.

## ADR-0023: Keep read-time effective-value queries after the performance spike

**Status:** Accepted

**Context:** ADR-0005 resolves fallback at read time, so filtering and sorting on translatable fields uses `COALESCE` over one join per language in the chain. The spike seeded 50,003 course pages with three languages (`l1 → pt → default`, Portuguese values on half the pages) and timed `lynx:bench` in the deepest chain. Measured on 2026-10-06 in a 2-core cloud container, median of 5 runs, including loading 20 hydrated pages:

| Case | SQLite | PostgreSQL 16 |
| --- | --- | --- |
| Shared field filter (`price<200`) | 44 ms | 27 ms |
| Translatable filter (`title%=`) | 136 ms | 140 ms |
| Translatable sort, first page | 67 ms | 118 ms |
| Translatable sort, page at offset 5,000 | 92 ms | 157 ms |
| Count (`price>100`) | 20 ms | 21 ms |

The totals of paged results are now counted only when `total()` is read, which roughly halved the PostgreSQL times of the translatable cases. Seeding 50,000 pages through `SavePage` took 7 minutes on SQLite and 14 on PostgreSQL.

**Decision:** Keep effective-value queries as the only query path for the MVP. Do not build the materialised search index yet. Revisit when a single template holds more than about 100,000 pages, when full-text search is needed, or when sorting a large listing by a translatable field becomes a common page.

**Consequences:** No denormalised data to keep in sync. Large catalogues sorted by a translatable field cost one sort over all candidate rows; the planned index (keywords and sort keys per page, field and language) remains the escape hatch. Bulk imports should use a batch path that skips per-page path rebuilds, to be added with export/import.

## ADR-0024: Sibling order as contiguous positions; pages.sort reorders, pages.move reparents

**Status:** Accepted

**Context:** The admin page tree (and later the menu builder) reorders pages by drag and drop and by keyboard. `pages.move` set `sort` to a raw value or `max + 1` and never renumbered siblings, so a position from the user interface could not be mapped to a row, and a reorder within the same parent went through the move path: closure rewrite, path rebuild and a misleading `PageMoved` event.

**Decision:** `sort` holds contiguous 0-based positions among siblings. `SiblingOrder` (internal) places a page at a position and renumbers only the rows that change. `pages.sort` (`page`, `position`) reorders within the current parent, records the change and fires `PageSorted`; it never touches paths. `pages.move` treats `sort` as a 0-based position among the new siblings, compacts the old siblings, and rejects moves to the current parent. `pages.status` toggles the editable flags (hidden, unpublished, locked) without re-validating field values. `PagesApi::childCounts()` counts children of many parents in one grouped query for tree views.

**Consequences:** Positions from the admin, MCP and the agent mean the same thing. Existing rows may start at 1 or have gaps until their parent is first sorted; order stays correct because reads sort by `sort, id`. Locked and system flags are only enforced by the admin for now; enforcing them in the actions needs a permissions model.

## ADR-0025: Menus are pages, rendered by any Blade view

**Status:** Accepted

**Context:** Sites need several menus (a top bar, a footer, a legal strip) with different structure and behaviour: one opens on hover, another opens on click like an accordion. Items are section labels in every language, pages (optionally with their child pages) and custom links with their own target. ADR-0008 already says menus are pages.

**Decision:** A separate `lynxcms/menus` package stores each menu as a page on the `menu` template under a hidden system page (`_menus`, template `menus`), and each item as a `menu-item` page below it, all with `has_url: false`. An item's kind follows from its values: a linked page makes it a page item, a URL makes it a link, neither makes it a section label. Writes go through `menus.install`, `menus.create` and `menus.save_item`; sorting, moving and deleting reuse `pages.sort`, `pages.move` and `pages.delete`. URLs are limited to relative paths, fragments, http(s), mailto and tel, and targets to browser keywords or frame names, both when saving and when rendering. `lynx()->menus()->get()` resolves a menu for the current language (labels with fallback, page titles, automatic child pages within the item's depth and limit, active trail), and `<x-lynx-menu>` renders it with any Blade view. Behaviour belongs to the view: the package ships a plain list, a CSS only hover dropdown and a `<details>` accordion, and sites write their own. The editor lives in the admin and reuses the page tree through a presenter and events.

**Consequences:** Menus get translations, fallback, ordering, the activity log and MCP and agent tools without new storage. Field names start with `menu_` and must not be taken by site fields of another type; the installer refuses that clash. Menu items show in the main page tree under a system branch. Depth and limit of automatic child pages are set per page item (defaults 1 level and 10 pages per level) so large sections cannot flood a menu, and two items can differ. Linked pages follow the visitor rules: hidden, unpublished, below an unpublished page (ADR-0026) or unavailable in the current language means left out. Items can show in chosen languages only (`menu_languages`, a list of codes, because toggles cannot be translatable and the default language's name row is always active) and carry icons rendered as CSS classes or by an app renderer.

## ADR-0026: Unpublished cascades to the whole branch; hidden does not

**Status:** Accepted

**Context:** Unpublishing a section page (for example a course catalogue under review) should take everything below it offline. Before this decision only the page itself returned 404 and left listings; its children stayed reachable and kept appearing in selectors and menus. Hidden means something else: it keeps a page out of listings, menus and search, but the page must stay reachable by its URL (a landing page shared by link only).

**Decision:** A page is published only when it and every page above it lack the `unpublished` flag. Page serving checks the ancestors through the closure table (`Pages\Publication`). Selectors exclude pages below an unpublished page unless `include=unpublished` or `include=all` is given: `Pages\UnpublishedBranches` lists unpublished pages that have children (one small query per request, flushed after every action), and only when that list is not empty do selectors add a `NOT EXISTS` on the closure table by `ancestor_id`. A first version tested the status of every ancestor row in the subquery; on PostgreSQL with 10,000 courses it made counts ten times slower (2.7 ms to 29 ms) and translatable sorts nearly three times slower, so it was replaced before release. `hidden` keeps its meaning: excluded from selectors unless included, still served by URL, and it does not cascade to children.

**Consequences:** Unpublishing one page is enough to take a branch offline, and publishing it again restores the branch without touching the children. With no offline branch, selectors pay nothing extra; with some, one indexed subquery (measured within noise on 10,000 pages). Menus, listings and the agent's `find_pages` follow the rule without extra code; the admin tree uses `include=all` and still shows the whole branch.

## ADR-0027: Locked and system pages are enforced by the page actions

**Status:** Accepted

**Context:** The `locked` and `system` flags existed, but only the admin's page tree respected them by hiding buttons. Any other caller (the pages API, MCP, the AI agent, imports) could edit, move or delete such pages, and a save could even remove the `system` flag. Modules such as the menu builder depend on their system pages (`/_menus/`) staying where they are.

**Decision:** Every page action checks the stored status through `Pages\PageGuard`, never the in-memory `Page`, so a changed object cannot unlock itself. A locked page cannot be saved, moved, sorted, switched to another template, translated or deleted, and `pages.status` may only change its `locked` flag; unlocking is the way back in. A system page cannot be deleted, moved to another parent, switched to another template or changed through `pages.status`, and a save keeps its `system` flag as stored (new pages may still be created as system pages by installers). Deleting a branch fails if any page in it is locked or system. Children may still be added under a locked page, pages may be moved into it, and its siblings may be reordered.

**Consequences:** The same rules hold for the admin, the API, MCP and the agent, and their errors explain how to proceed. There is no override yet: removing a module's system pages (an uninstall) needs a privileged path, to be designed with roles and permissions.


## ADR-0028: The unique status keeps a default-language name free across the whole tree

**Status:** Accepted

**Context:** Page names only have to be unique among siblings, so `/courses/intro/` and `/guides/intro/` can coexist. Some pages need a name nothing else uses, as in ProcessWire's unique status: a landing page, a page that integrations look up by name, or one whose name must never be confused with another. The `unique` flag existed in `PageStatus` but nothing enforced it, and `pages.status` refused it.

**Decision:** `unique` is an editable flag of `pages.status`. A unique page's name in the default language is used by no other page anywhere in the tree, and no other page may take it. `Pages\UniqueNames` checks both rules, case-insensitively, whenever `pages.save` writes a default-language name (new page or rename) or adds the flag, and whenever `pages.status` adds the flag; the error names the page that holds the name. Other languages are not checked, the home page (which has no name) is never affected, and moving a page needs no check because its name does not change. Removing the flag frees the name.

**Consequences:** Editors, the API, MCP and the agent share one rule and one error. The check costs a query on the default-language names only when a name is written or the flag is added. The URL shortcut to unique pages relies on this guarantee (ADR-0029).

## ADR-0029: Page references and the unique URL shortcut follow ProcessWire

**Status:** Accepted

**Context:** `$lynx->pages->get()` read a bare name as a path from the root and an all-digit string as an id, and the page actions resolved references in their own trait. ProcessWire's `$pages->get()` reads a bare page name as `name=…` at any depth, and its router sends a one-segment URL to a unique page anywhere (`PagesPathFinder::getShortcutGlobalUnique()`). Developers coming from ProcessWire expect both.

**Decision:** One resolver, `Pages\PageReferences`, follows ProcessWire's `normalizeSelectorString()`. A PHP integer or an all-digit string is an id. A string with a slash is a path in the default language and nothing else (`/courses/alpha/` looks only under `/courses/`; unlike ProcessWire, the leading slash is optional). In `get()` a string with `=` is a selector. A bare page name is `name=…` in the default language at any depth, whatever the page's status, and the first page in sort order wins; values that are not page names match nothing. Page actions (the API's writes, MCP and the agent) use the same rules, except that a bare name must belong to exactly one page, otherwise they refuse and ask for the path or id. The front end adds ProcessWire's shortcut: a one-segment URL in the default language that matches no page answers with a 301 to the published unique page with that name.

**Consequences:** `get("alpha")` works as in ProcessWire, and writes cannot hit the wrong page because two pages share a name. A page named `2024` is reached by its path (`/2024/`), since `"2024"` is an id. Unique pages get short URLs that always redirect to their real one.

## ADR-0030: Field conditions use ProcessWire's showIf syntax and are enforced by pages.save

**Status:** Accepted

**Context:** ADR-0019 put declarative `showIf` and `requiredIf` conditions in the template's field settings, and templates stored `show_if` and `required_if`, but nothing defined their syntax or evaluated them: a template could declare a rule that was silently ignored. Site builders coming from ProcessWire know its showIf strings.

**Decision:** A condition is written like ProcessWire's showIf: clauses separated by commas, all of which must match, each `field operator value` with the operators `=`, `!=`, `>`, `<`, `>=`, `<=` and `%=` (contains, any case); `|` separates alternative values and an empty value means empty (`summary=`). Numbers compare as numbers, toggles as 1 and 0, page references by id. Conditions read the shared value or the default language's (like required fields, ADR-0006). `Templates\FieldCondition` parses and evaluates them. Writing a template refuses conditions that do not parse, name fields the template lacks, or name the field itself. `pages.save` enforces them: a field is required when it is shown and either flagged required or its `required_if` matches; a field `show_if` hides is never required. The admin evaluates the same class on the server against the form as it is (fields a condition reads update the form as you type), so the editor and every other caller agree.

**Consequences:** One implementation serves the editor, the API, MCP and the agent. Hidden fields keep their stored values. Conditions cannot yet read other languages, other pages or the page's status.

## ADR-0031: Text formatters run at output, after sanitizing on save

**Status:** Accepted

**Context:** ProcessWire attaches ordered textformatters to a field and runs them when a template outputs the value, leaving the stored value untouched. Lynx has one fixed output step per textarea `format` (escape, Markdown, or HTML as is) and sanitizes HTML on save with `HtmlCleaner`.

**Decision:** Fields get an ordered `formatters` setting. Formatters are registered like field types and run when a template outputs a value, as ProcessWire's output formatting does: `{{ $page->body }}` renders formatted (Blade prints `Htmlable` values through `toHtml()`, where the chain runs after the conversion the field's `format` already does), and shared text fields return a value object so they format too. `$page->getUnformatted('body')` returns the stored value, as in ProcessWire. `value()`, string casts, JSON, the API, MCP and agents are never formatted. A chain that may produce HTML ends with `HtmlCleaner`. Sanitizing on save stays: formatters shape output, they are not a security boundary for stored data.

**Consequences:** Shared text and textarea fields now return a `SharedValue` instead of a string, so code comparing them strictly (`$page->target === '_blank'`) must use `getUnformatted()` or cast; `Value::string()`, string casts and Blade keep working. Formatters can be added, removed and reordered without touching content. Output costs a little more per field, kept low by per request memoization. Templates get formatted output by default and ask for raw data explicitly; code that builds strings from `(string)` casts keeps getting stored values.

## ADR-0032: Template family rules decide which templates may sit under which

**Status:** Accepted

**Context:** ProcessWire's template Family tab sets whether pages may have children, which templates children may use and which templates the parent may use. Lynx only had `allowed_templates`, the templates a page may switch to, so the New page screen offered every template under every parent and nothing stopped an agent from placing a course under a menu.

**Decision:** Templates get three settings: `allow_children` (default true), `child_templates` and `parent_templates` (template names; empty means any). `Templates\FamilyRules` checks a page's template against its parent's: the parent must allow children, the child's template must be in the parent's `child_templates` when that is set, and the parent's template must be in the child's `parent_templates` when that is set. `pages.save` checks new pages, `pages.move` the destination, and `pages.switch_template` both the page's parent and the page's existing children. The admin's New page screen and template switch offer only templates the rules allow, and `describe_schema` reports the rules to agents. Names are not checked against existing templates when saved, so schema apply can set rules before every template exists.

**Consequences:** One rule set for the admin, the API, MCP, the agent and imports. Existing pages that break a rule added later stay where they are; the rules apply to the next create, move or switch. Root pages have no parent and are never checked.

## ADR-0033: Roles and permissions are enforced in core, without locking anyone out on upgrade

**Status:** Accepted

**Context:** One gate (`accessLynxAdmin`) let any signed in user do everything in the admin, and MCP clients, the agent and the pages API were not limited at all. Sites with several editors need "blog editors only edit the blog" and "only publishers publish", and agents that can do no more than the person behind them.

**Decision:** Lynx owns roles in its own system tables (`lynx_roles`, `lynx_role_permissions`, `lynx_role_user`); host app users are mapped to roles, never owned. A role grants permissions from a registry core fills and modules extend (`lynx()->permissions()`); a grant can be limited to template names and to branches of the tree (a page and everything below it), and a superuser role passes every check. `Permissions\Authorizer` answers for an actor: users, and MCP clients and agents acting for a user, have the union of that user's roles; MCP clients and agents without a user get `lynx.permissions.anonymous_role` or nothing; the console and the system actor have every right. Until any user has a role, signed in users keep full rights, so an upgrade or a fresh install never locks anyone out; the first assignment switches enforcement on for everyone.

Every action declares the permission it needs (`Action::permission()`, superusers only by default); the runner checks it before the action runs, page actions check the exact page and template, and actions run inside another are not checked again. A module can delegate its own branch to its permission (the menus module: `menus.manage` covers page actions under `/_menus/`).

Install creates a `superuser` and an `editor` role; `lynx:install --user=ID` makes that user a superuser. The last superuser assignment can never be removed. Schema sync exports roles and grants (branches as paths, since page ids differ between environments) but never who holds them. The admin hides what the current user cannot use, asking for the same actor the actions run as, and offers Roles and Users screens to superusers.

**Consequences:** One rule set for the admin, the API, MCP and the agent. A site with no role assignments behaves as before; the README says so, and `lynx:install --user` makes the first superuser. `find_pages` lists only the pages a user's `pages.view` grants cover, by template and branch, and counts them the same way. The admin page tree shows only the pages a user may view or act on (any page action) and the pages above them as the way there; a level with hidden siblings cannot be reordered, since positions among the visible rows are not positions among all siblings. A module's permission (such as `menus.manage`) counts as page rights inside the module's branch for these lists too. A permission can require others in the same role: `menus.manage` requires `pages.view` (linking a page means finding it), the menu page search and the page picker list only pages the user may view, and linking a page in a menu checks `pages.view` on it.


## ADR-0034: The admin is built on BlatUI components and tokens

**Status:** Accepted

**Context:** The admin had its own component classes (`lynx-button`, `lynx-input`, `lynx-panel`…) and its own `--lynx-*` colour variables, with one bundled preset (Flux). Every new screen meant more hand made controls, and themes had to be written against a token set nobody else uses. BlatUI is shadcn/ui for Blade: owned copies of accessible components, Livewire aware, with a well known token set (`--primary`, `--background`, `--muted`…) and ready presets.

**Decision:** The admin uses BlatUI. Its components are copied, through BlatUI's MCP server, into `packages/admin/resources/views/components/ui` and used as `<x-lynx-admin::ui.*>`; the admin owns and may patch them (each patch is commented). Its foundations are copied too: the tokens, the `@theme` mapping and the preset dimensions (`resources/css/blatui.css`), and its Alpine core (`resources/js/blatui-core.js`), registered on the Alpine that Livewire starts. Controls bound with `wire:model` that need Blade directives inside the tag use BlatUI's native classes (`blat-input`, `blat-select`, `blat-checkbox`), which mirror the components. The shell is a BlatUI sidebar with breadcrumbs. The page tree keeps its own component and behaviour (ProcessWire style selection, pager, move mode) and borrows the look of BlatUI's server tree table.

Theming uses BlatUI's tokens. `lynx-admin.appearance` picks presets (base colour, accent, radius, font, shadow, spacing, input style) as attributes on `<html>`, `lynx-admin.theme` picks light, dark or system, and stylesheets in `lynx-admin.stylesheets` can set any token, including themes copied from BlatUI's customizer. Only the tree keeps `--lynx-*` tokens, each defaulting to a BlatUI token. The Flux preset and the other `--lynx-*` colour variables are removed.

BlatUI components call `$attributes->twMerge()`. The admin registers that macro itself on `gehrisandro/tailwind-merge-php`, because the Laravel bridge pins Guzzle 7, and leaves it alone when a host app already defines it. Icons come from `mallardduck/blade-lucide-icons`.

**Consequences:** Host apps that styled the admin with `--lynx-*` colour variables or the Flux preset must move to BlatUI tokens or `lynx-admin.appearance`. The admin gains two runtime dependencies (tailwind-merge-php, blade-lucide-icons) and a larger script (BlatUI's Alpine core with floating-ui). Updating a component means fetching it again from BlatUI and reapplying the commented patches. New screens are built from the same components, so the admin, the menus module and paid modules look alike.

## ADR-0035: Deleting goes through a trash that can never trap pages

**Status:** Accepted

**Context:** `pages.delete` removed a branch for good. ProcessWire moves deleted pages to a trash where they can be restored. Done naively, a trash traps pages: a branch trashed with a locked page inside cannot be emptied because of the lock, and if the trash offers no unlock it cannot leave either. The rules against that were decided first (`documentation/plans/trash.md`).

**Decision:** `pages.trash` moves a page and everything under it below a system page `/_trash/` (template `trash`: hidden, unpublished, no URL), created the first time something is trashed. It checks what a delete checks: a locked or system page anywhere in the branch refuses it, naming that page, and `pages.delete` is needed on every page. The top page is renamed `{id}-{name}` in every language, and a `lynx_trash` row records the original parent, position, names, the ancestors of that place, and who trashed it when. Trashed pages have no URL, and because the trash is unpublished their old URLs answer 404 instead of redirecting into it.

`pages.restore` puts a branch back where it was, with its names and URLs; when that parent no longer exists or is itself in the trash, it asks for a new parent, and it refuses when a sibling took the name. Locks never block it. `pages.empty_trash` deletes all trashed branches or one: a branch holding a locked page stays and is reported with its locked pages, a branch the user may not delete stays too, and the rest is deleted. Inside the trash, permissions follow the original place: a page there also counts the ancestors recorded when it was trashed, so branch limited grants still reach it (unlock with `pages.status`, restore and empty with `pages.delete`). All three need `pages.delete`.

**Consequences:** `pages.delete` stays for deleting directly, and also deletes pages already in the trash. Agents are told to prefer the trash. The trash page is a singleton system page like `/_menus/`. Emptying old trash automatically is still open.

## ADR-0036: Scheduled publishing per language, applied every minute

**Status:** Accepted

**Context:** Lynx 1 could publish a page, or one of its languages, at a set time. Pages are published in the default language through the unpublished status, and in other languages through whether the language is active for the page.

**Decision:** `pages.schedule` stores, per page and language, a publish and an unpublish time in `lynx_schedules`. It needs `pages.publish` on the page, only accepts future times, and refuses an unpublish time at or before the publish time; a key given as null clears that entry. `lynx:schedule:run`, registered in Laravel's scheduler every minute, runs `pages.run_schedules`: due entries are applied oldest first through the normal actions (`pages.status` for the default language, `pages.save` with the language's active flag for the others), so hooks, activity and checks apply. An entry that cannot run, such as one for a locked page, is marked failed with the reason and not retried; the others still run. Deleting a page or a language removes its entries. The admin edits them in the Settings tab, in the app's time zone.

**Consequences:** Publishing happens within a minute of the time, and only where the host app runs Laravel's scheduler (`schedule:run` from cron); without it nothing is applied, and the README says so. Pages are not hidden or shown at read time by the schedule itself. A failed entry stays visible in the admin until it is cleared or given a new time.

## ADR-0037: Edit locks keep two people from saving over each other

**Status:** Accepted

**Context:** Two editors with the same page open both saved, and the revision check (`expected_revision`) only told the second one after the fact, losing their edits. Lynx 1 locked a page while it was being edited.

**Decision:** `pages.lock_edit` (needs `pages.edit`) takes or renews an edit lock on a page in `lynx_edit_locks`: who holds it and until when. It lasts `lynx.edit_lock_seconds` (120 by default) unless renewed, and an expired lock is free to take. While someone holds a lock, `PageGuard` refuses every write to the page by anyone else (saves, status, moves, sorting, template switches, translations, deletes, trashing), naming the holder; the console and the system actor (scheduled publishing, imports) are not held back. `pages.unlock_edit` releases a lock: the holder's own, or anyone's for a superuser. Neither is offered to MCP or the agent, and renewals are not written to the activity log. The admin's page editor takes the lock when it opens and renews it every minute; when someone else holds it, the editor is read only and names them, the same poll picks the lock up once they leave, and superusers can take over.

**Consequences:** A closed tab blocks others for at most two minutes. The same person in two tabs holds one lock, and the revision check still catches conflicts between those tabs. Agents and API clients get a clear refusal while a page is open in an editor and can try again later. The edit lock is unrelated to the locked status (ADR-0027), which protects a page until it is unlocked.

## ADR-0038: Revisions after every save that changes values

**Status:** Accepted

**Context:** A save replaced values for good: the activity log said which fields changed, not what they held, so a bad edit could not be undone. Drafts (next) also need somewhere to keep what a publish replaced. Decided with the drafts plan (`documentation/plans/drafts-and-revisions.md`).

**Decision:** After every `pages.save` that writes field values, the page's values in every language (`Page::valuesByLanguage()`, "*" for untranslated fields) are stored in `lynx_revisions` with the page's revision number and who saved. The newest `lynx.revisions.keep` (50) per page are kept. `pages.restore_revision` (needs `pages.edit`) saves a revision's values back through `pages.save`, for the fields the template has now, clearing values the revision did not have; the save records a new revision, so restoring can itself be undone. Deleting a page deletes its revisions. The admin lists revisions in the Settings tab, compares one with the page in the review table, and restores it. Agents find revision ids in `get_page_history` and restore with `pages_restore_revision`.

**Consequences:** Every value save writes one more row, holding the whole page's values; pruning keeps the table bounded. Names, status, template and place are not part of revisions (moves and renames keep their old URLs, ADR-0011). Status-only saves record nothing.

## ADR-0039: One shared draft per page, stored as a page row

**Status:** Accepted

**Context:** Editors need to prepare changes to a live page without visitors seeing them half done. Lynx 1 had drafts; Craft stores drafts as element rows. Decided with the plan (`documentation/plans/drafts-and-revisions.md`): draft rows, one shared draft per page.

**Decision:** A draft is a row in `lynx_pages` with `draft_of` set to the live page (unique, so one per page), hidden and unpublished, with no parent, names, paths or closure rows, and its values in the same field tables. Selectors, the root page lookup and the singleton checks leave drafts out, so they never appear in finds, counts, the tree or URLs. `pages.save_draft` (needs `pages.edit` on the live page, and its locks apply) creates the draft from the live values the first time and saves values to it through `pages.save`, so they are validated like a save; a draft's saves are not revisions. `pages.publish_draft` saves the draft's values onto the live page through `pages.save` (validation, hooks, activity and a revision, ADR-0038) and deletes the draft; `pages.discard_draft` deletes it. Drafts cover field values only: names, status, template and place stay on the live page. Switching the template is refused while a draft exists, and deleting a page deletes its draft. The admin's editor shows the draft's values while one exists, saves to it (also with Cmd/Ctrl+S), and offers Preview draft, Publish draft and Discard draft; the tree marks pages that have a draft. Adding `?preview=draft` to a page's URL turns draft previews on for the signed in user's session, so following links and switching language keep showing drafts, until `?preview=live` (the Exit preview link in a bar the preview adds to the page); pages they may edit show their draft values, with `Cache-Control: no-store` and `X-Robots-Tag: noindex`. Names and URLs are live, so the editor offers a preview link per language the page is published in. Everyone else gets the live page.

**Consequences:** Field types, the page loader and the review table work on drafts unchanged. A draft started before the live page changed keeps its own copy: publishing it replaces every value, including ones changed on the live page meanwhile (the revision keeps them).

## ADR-0040: Content packages for export, import and backups

**Status:** Accepted

**Context:** Lynx 1 could export content and restore backups. Schema already travels as YAML (lynx:schema:export); pages did not, so content could not move from staging to production, between sites, or be restored after a bad change.

**Decision:** A content package is a directory (or a .zip of one) with `manifest.json` (format `lynx-content`, version 1), `schema/` (the schema export) and `pages.jsonl`: one page per line, parents first, keyed by its path of default-language names (`/courses/alpha/`, which also works for pages without URLs, such as menu items), with its template, status, names and published flag per language, and values per language ("*" for untranslated fields). Page references are written as keys, so they resolve on another site. Drafts, the trash and the system flag are left out. `lynx:export` writes one; `lynx:import` applies it through the `content.import` action (superusers only, not an MCP or agent tool): pages are matched by key, created when missing and saved through `pages.save` when they differ, page references are set once every page exists, `--with-schema` applies the schema first, `--prune` moves pages not in the package to the trash, and `--dry-run` writes nothing. A page that cannot be written (locked, or on another template here) is skipped with the reason. `lynx:backup` writes a timestamped .zip to `lynx.backups.path` (default `storage/app/lynx/backups`), keeps the newest `lynx.backups.keep` (14), and runs hourly or daily when `lynx.backups.schedule` says so; restoring is `lynx:import <zip> --with-schema --prune`.

**Consequences:** Importing a site's own export changes nothing, so imports are safe to repeat. Packages are content backups, not database backups: activity, revisions, edit locks, schedules, role assignments, users and app tables are not in them; the README says to back up the database as well. Media files are not part of packages until Lynx has a media library.

## ADR-0041: Admin sign-in with Laravel Fortify, without registration

**Status:** Accepted

**Context:** The admin relied on the host app for signing in. Sites built only on Lynx had no login screen, and a CMS should not let visitors register: editors are invited by a superuser. The Laravel starter kits sign users in with Laravel Fortify (login, password reset, two-factor authentication, passkeys), which is maintained by Laravel and battle tested.

**Decision:** The admin package requires `laravel/fortify` and configures it as Lynx's sign-in: routes under the admin prefix (`/lynx/login`, `/lynx/forgot-password`, `/lynx/reset-password/{token}`, `/lynx/logout`), Lynx's own views on the admin's BlatUI components (no Flux, no starter kit layout), home `/lynx/pages`, lowercase emails, five attempts a minute per email and IP, and no registration. `lynx-admin.auth.enabled` (`LYNX_ADMIN_AUTH`) is `auto` by default: Lynx configures Fortify unless the app has its own `config/fortify.php`, in which case the app's screens sign users in and Lynx leaves Fortify alone. Turned off, Lynx tells Fortify not to register routes, so its defaults never offer `/register`. Passwords follow the app's `Password::defaults()`. The admin's stylesheet and script are served without the auth middleware, since the sign-in screens need them. Users stay the host app's (ADR-0033); signing in is not a Lynx action and is never an MCP or agent tool. Superusers invite users from Setup > Users through `users.invite` (not an agent tool, since it emails any address): it creates the user through `LynxUsers` with an unusable random password, sets their roles and, after the transaction commits, emails a link to `/lynx/invitation/{token}` where they choose a password and are signed in. Invitation tokens come from their own password broker on the app's reset table, so they last `lynx-admin.auth.invitation_days` (7) instead of the hour of a password reset. Pending invitations are rows in `lynx_invitations` (an admin table, read through the `Invitation` model), removed when the user sets a password from the invitation or a password reset; `users.resend_invitation` emails a pending one again with a new link, which invalidates the old one. Account > Security (`/lynx/account/security`, behind password confirmation) changes the password and manages two-factor authentication (enabled only once a code confirms it, with recovery codes) and passkeys, through Fortify's actions and `@laravel/passkeys` bundled in the admin script. The sign-in screen offers passkeys and the two-factor challenge. Both features show when the app's user model uses Fortify's `TwoFactorAuthenticatable` and `PasskeyAuthenticatable`; a guarded admin migration adds Fortify's two-factor columns and the `passkeys` table when the app does not have them.

**Consequences:** A new site has a working admin login after installing Lynx. Apps that already use Fortify keep full control. Password reset emails need a notifiable user model, as Laravel's own reset does. Two-factor authentication and passkeys build on the same Fortify setup and need its columns and the passkeys table on the app's users.

## ADR-0042: Field types present their own values and declare their references

**Status:** Accepted

**Context:** Lynx checked field type names in many places: `Page::get()` wrapped `page` values in a `PageArray` and text values in `SharedValue`; eager loading, backlinks, content export and import knew only the `page` type's references; selectors and the admin's form conversions matched type names. A module type (images, repeaters) could store data but could not present a rich value, take part in backlinks or move between sites.

**Decision:** Optional interfaces in core, which module types implement as needed: `PresentsValue::present($value, Field)` returns what templates read for shared fields (translatable fields stay `TranslatableValue`; `getUnformatted()` always returns the stored value); `ReferencesPages` gives `pageIds()` (eager loading), `mapPageIds()` (page ids to keys in content packages and back) and `whereReferences()` (backlinks). The `FieldType` contract gains `comparison()`, a `Comparison` case (`Text`, `Integer`, `Boolean`, `None`) that selectors use instead of type names; `None` refuses the field in selectors. In the admin, `FieldInputs::register($type, $view, $converter, $display)` registers a `FormConverter` (stored value to form value and back, optionally `PreparesDisplay`) and a read-only display view with each input. The core types use the same mechanisms, so no type name is special any more. The interfaces live in core rather than contracts because they take core types (`Field`), and module types extend core's `BaseFieldType` anyway.

**Consequences:** New field types (Phase 2 onwards: repeater, matrix, images) plug into reading, selectors, backlinks, content packages and the editor without touching those parts again. Third-party types implementing `FieldType` directly must add `comparison()`; `BaseFieldType` returns `Text`.

## ADR-0043: Schema text per language

**Status:** Accepted

**Context:** Field and template labels and descriptions were one string, so a site edited in Portuguese and English showed every editor the same words. Repeater and matrix fields add more of this text (subfield labels, type labels, the add button).

**Decision:** Schema text editors read is a `LocalizedText`: a plain string (the same in every language, as before) or a map of language codes to text. It covers field `label`, `description` and `notes` (a short hint under the input), template `label`, the label, description and notes a template gives a field, and later repeater and matrix settings. Reading picks the current language, then its fallback chain, then the default language, then any text. `$field->label` and `$template->label` return the text in the current language; `labels()` returns every language; actions and schema files take and write a string or a map, and codes that are not languages are refused. Label columns hold the string, or the map as JSON (now `text`). When a language's code changes, `languages.update` rewrites these maps in the same transaction. The admin's forms show one input per language as compact tabs; text given only in the default language is stored as a plain string.

**Consequences:** Existing labels keep working unchanged. Maps are keyed by code (readable in schema files), which is why a code rename rewrites them. The admin shows labels in the admin's current language, which is the default language until an interface language exists.

## ADR-0044: Item fields store one row per item and language

**Status:** Accepted

**Context:** Sites need lists of structured items on a page: credits (a partner and a role), services, slides. ProcessWire stores repeater items as hidden pages under an admin branch, which needs a template per field, hides them from searches with hooks, and leaves orphan parents. Lynx drafts and revisions copy a page's own field rows, not child pages, so items as pages would also miss drafts and revisions.

**Decision:** A repeater is a field type (`repeater`) whose items are rows of its own table, `lynx_f_{name}`: `page_id`, `item` (a ULID that stays the same across saves, languages and drafts), `language_id`, `sort`, `enabled`, `depth`, `type` and `data` (JSON), primary key `(page_id, item, language_id)`. The row with `language_id` 0 holds the item (order, on/off, indent depth, matrix type) and its shared subfield values; one row per item and language holds that language's translatable subfield values, so language codes never enter the database and fallback still happens at read time, per subfield. Subfields are existing fields listed in the field's settings (`fields: [{name, required, label, description, notes, width}]`), so their types normalize and validate the values; fields that store items themselves cannot be subfields (no nesting; `max_depth` indents instead). The field itself is shared, so writing, drafts, revisions, deleting and purging handle the table like any other. The value API, revisions and content packages use one shape, a list of `{key, type, enabled, depth, values}` where a translatable subfield's value is a map of language codes; `pages.save` merges input for one language into the stored items (`MergesInput`), matching items by key, and items left out are removed. Templates read an `ItemList` of enabled items (`all()` includes the ones switched off); each `Item` presents its subfields like page fields (`TranslatableValue`, `PageArray`). Item fields take part in backlinks, eager loading and content packages through `ReferencesPages`, with a JSON containment helper because Laravel's `whereJsonContains` breaks on SQLite under a table prefix. `pages.translate` fills missing item translations; items keep no translation origin, so `overwrite` replaces any.

**Consequences:** Items get drafts, revisions, trash and content packages without special cases. Subfield selectors (`credits.partner=7`, `credits.role%=Real`, `blocks.type=video`, `credits.count>0`) compile to an EXISTS over the item table's enabled item rows, reading JSON per driver; translatable subfields COALESCE the item's language rows along the chain, and `!=` means no enabled item matches. Sorting by a subfield is not supported. Changing a matrix item's type keeps the other type's values in `data` (rule 6).

## ADR-0045: Matrix types are stable slugs in field settings

**Status:** Accepted

**Context:** Pages built from blocks (text, image, video, quote) need items of different shapes in one ordered list. ProcessWire's RepeaterMatrix numbers its types and keeps them as `matrix{n}_*` field properties, so reordering or copying types between fields can change what an item is, and per-type overrides do not travel with the type.

**Decision:** `matrix` is an item field (ADR-0044) whose settings list `types`: `{slug, label, fields, item_label}`, with the same subfield entries as a repeater and labels per language. Each item stores its type's slug in the item row's `type` column; slugs never change, so types can be reordered, renamed in labels or copied without touching items. An item shows and validates only its type's fields; changing its type keeps the other type's values in `data`, hidden (rule 6). A template field may offer only some types (`allowed_types`), checked on save. Templates render blocks with `@matrix($page->blocks)` or `$block->render($page)`: the view `{views_path}.fields.{field}.{template}.{type}`, then `{views_path}.fields.{field}.{type}`, gets `$block`, `$page` and `$loop`; without one a block renders nothing, or an HTML comment naming the missing view when `app.debug` is on. Selectors match types with `blocks.type=quote`.

**Consequences:** Type definitions travel with the field in schema files. Changing a type's field list leaves values of removed fields in place until purged. Types defined in PHP classes may come later.

## ADR-0046: Media lives with its page; files outlive values until pruned

**Status:** Accepted

**Context:** Sites need images and documents on pages, with ProcessWire's comforts: several images per field, sortable, a description per language, tags, a focus point that crops respect, and sizes made on demand. A central media library is a larger product and can come later.

**Decision:** A new package, `lynxcms/media`, keeps core free of image libraries. Its `images` and `files` field types are item fields (ADR-0044): one item per file, switchable and sortable, the description stored per language like any translatable value. Values hold `{page id}/{file}` (plus size, type, dimensions, focus point and tags); the files live on `lynx-media.disk` (default `public`) under `{root}/{page id}/`, so a draft, a revision or a restored trash page pointing at a value finds the same file. `media.store` writes a file from an admin upload, a URL or base64 (uploads only from the admin, never a path an agent names), checks the field's extensions, size and dimensions, scales large images down, removes the file if the action rolls back, and returns the entry for `pages.save`. Changing a value never deletes a file: `media.prune` (daily, `lynx:media:prune`) deletes files that no media value and no revision refers to once older than `prune_after_hours`, and a page deleted for good takes its folder with it. Images resize lazily with Laravel's `Image` facade (`intervention/image`): `size()` crops around the focus point, `resize()` keeps the ratio without upscaling, `webp()` converts, and `preset()` names sizes from config; each size is stored beside the original under a name that says how it was made, so later requests reuse it. `<x-lynx-image>` writes the img tag with srcset, alt and lazy loading. In the admin, a Livewire field component uploads, sorts, hides, removes, describes, tags and sets the focus point.

Media fields may also be repeater and matrix subfields (`NestsInItems`): their items then sit in the item's JSON, `media.store` accepts them, and `media.prune` scans item tables and revisions for every stored file.

A file needs its page's folder, so media types implement core's `NeedsSavedPage`: `pages.save` creates a page unpublished without a required media field, and asks for it on every later save and when the page is created published. The New page screen leaves such fields for the editor and creates the page unpublished.

**Consequences:** Content packages did not carry media files at first; ADR-0050 adds them. Selectors did not look inside media values at first; ADR-0049 adds that. Sizes are made during the request that first asks for them.

## ADR-0047: Template strings: code holds the default, the database holds only edits

**Status:** Accepted

**Context:** Every site has short texts that belong to the design rather than to a field: a hero kicker, button labels, section titles, a footer note. Adding a field for each bloats templates and schemas; hard-coding them means a developer for every word change. ProcessWire's FieldtypeFunctional lets templates declare such strings and editors change them, but finds them with a regex and keys them by text hash.

**Decision:** Templates declare strings with `@string('hero.title', 'Produções audiovisuais', label: 'Hero title', scope: 'page'|'site', format: 'plain'|'markdown')`; the code text may be a map of language codes. Keys are explicit; key, text and options must be literals, so `StringScanner` reads them with PHP's tokenizer without running the template, following `@include`, `@extends` and `<x-…>` components. `StringCatalog` caches each template's strings until one of the scanned files changes; `lynx:strings:scan` lists them and reports keys that are not literals and keys declared twice with different texts. Only edits are stored, in `lynx_strings` (page, key, language, value), so a changed code text reaches every page nobody edited. Printing reads the edit in the current language, then along its fallback chain, then the code text in that language; output is escaped, and `markdown` strings render Markdown with HTML in the text escaped. Site strings are stored on the home page and shared by every page. `strings.save` (an MCP and agent tool) edits strings of a page's template, or site strings from any page; null puts the code text back. `get_page` lists a page's strings with their edits, content packages carry a page's edits, and deleting a page deletes its edits. The admin shows a Texts tab on pages whose template declares strings, and Setup > Site texts.

**Consequences:** Drafts and revisions did not cover strings at first; ADR-0048 adds them. A string renamed in code leaves its old edits unused until the page is deleted.

## ADR-0048: A page's own strings are page content: drafts and revisions carry them

**Status:** Accepted

**Context:** ADR-0047 stored string edits straight away, so an editor working in a draft changed the live page's texts, and a revision restore left them as they were. Editors expect the Texts tab to behave like the fields beside it.

**Decision:** `pages.save` takes `strings`, the page's own edits by language code (`{"pt": {"hero.title": "Texto"}}`, null removes one), validated against the page-scope strings its template declares; removing an edit is always allowed. They are written with the values, listed in the activity, and make the save a revision. `strings.save` sends a page's own strings through `pages.save`, or through `pages.save_draft` with `draft: true`. A new draft copies the page's edits; publishing it, and restoring a revision, replace the page's edits with the draft's or the revision's through `pages.save`. Revisions store the edits in a `strings` column; revisions made before it have none and restore without touching strings. A draft preview shows the draft's strings (`Page::contentId()`). Site strings stay outside all of this: every page shares them, so they are always live and cannot be drafted. In the admin, the Texts tab edits the draft while the page has one, and a live save tells the editor about the new page revision.

**Consequences:** Saving texts moves the page's revision on, like any save. A string renamed in code keeps its old edits in revisions until they are pruned.

## ADR-0049: Selectors look inside files, top level and nested in items

**Status:** Accepted

**Context:** Templates ask for pages by their files: projects with a hero-tagged image, pages whose gallery has a wide image, matrix blocks holding images. Media values are item fields (ADR-0046), but their values are not subfields, so `gallery.tags=hero` was refused, and selectors stopped at one level of items.

**Decision:** Item field types may implement `HasItemProperties` and name the values each item holds, each an `ItemProperty` with a comparison, and flags for lists and translatable values. Media types offer `file`, `mime`, `bytes`, `tags` (a list) and `description` (translatable); images add `width` and `height`. `gallery.tags=hero` compiles like a subfield clause on the item rows; `description` reads the language rows along the chain, like translatable subfields. For such a field nested in repeater or matrix items, a third segment reads into the item's JSON: `blocks.gallery.count>0`, `blocks.gallery.tags=hero`, `blocks.gallery.description%=making`, through `json_each` on SQLite and `jsonb_array_elements` on PostgreSQL. Only switched-on items and files count, at both levels; `!=` means none matches. `ItemPropertyCompiler` holds this SQL and is the only other class besides the selector compiler, `ClauseComparison`, `JsonSql` and `SubfieldCompiler` that may mark SQL as trusted.

**Consequences:** Nesting stops at one item level, which is all items allow. Lists match with `=` and `!=` only. Sorting by a property is not supported.

## ADR-0050: Content packages carry the files pages use

**Status:** Accepted

**Context:** A content package (ADR-0040) moved pages between sites, but media values name files by the source page's id ("42/still.jpg") and the files stayed behind, so imported images pointed at nothing.

**Decision:** Field types whose values name stored files implement core's `CarriesFiles`: `files()` lists them and `mapFiles()` rewrites them. Media types answer for their own items; repeaters and matrices ask their subfields, so nested images count too. Core defines `PageFiles` (read a stored file, write one into a page's folder) and the media package binds it to its disk; without a binding, packages carry no files and nothing changes. Export copies each named file into `files/{stored name}`. Import handles these values in the pass that sets page references, once every page has its id: each packaged file is stored in the folder of the page it lands on, reusing a file of the same name and contents, and the value is rewritten to the new name. A dry run writes nothing. File names from a package are checked to be plain relative paths before they are read.

**Consequences:** Packages grow with their files. A file written by an import that then rolls back stays on disk until `media.prune` removes it. Importing into a page with a different file of the same name stores a copy under a new name each time.

## ADR-0051: Strings in layouts are site strings by default

**Status:** Accepted

**Context:** ADR-0047 made every `@string` a page string unless it said `scope: 'site'`. A developer who put a footer note in the site layout expected it under Site texts; instead each page got its own copy in its Texts tab. A layout's texts are almost always meant for the whole site.

**Decision:** A `@string` call that names no scope takes it from the folder of the view it is in: views under a folder listed in `lynx.strings.site_folders` (default `layouts`) declare site strings, every other view page strings. An explicit `scope` always wins. The scanner and the Blade directive apply the same rule from the view's path, the directive while compiling (`StringRenderer::renderIn()`), so what editors see and what visitors get always agree. Page overrides of site strings and edits per template were considered and left for later (docs roadmap).

**Consequences:** A layout string that was a page string becomes a site string after upgrading, and its page edits stop showing; add `scope: 'page'` to keep the old behaviour. Compiled views must be cleared (`view:clear`) for the new default to apply.

## ADR-0052: Forms are a module, defined in code first

**Status:** Accepted

**Context:** Sites need forms whose submissions the team can read and sort, with the people who handle them told at once: a quote request first. A form builder will follow, so the pipeline behind a form must not depend on where the form is defined.

**Decision:** A new package, `lynxcms/forms`, keeps forms out of core. A form is a definition (`Form`, with `FormField` and `FormStep`) registered by key in `FormRegistry`. The first source is PHP classes; the builder will add forms made in the admin through the same contract. `FormValidator` turns a definition into Laravel validation under `data.`, only for the fields the answers show, each group row on its own, with labels and messages in the visitor's language. `forms.submit` stores the submission in `lynx_form_submissions` with a snapshot of the fields, so the admin, mail and exports read old answers after the form changes. It keeps files on a private disk (`lynx_form_files`) and fires `FormSubmitted` after commit. The listener mails the form's recipients, else the users whose roles grant `forms.view` (`PermissionHolders`, in core), else configured addresses. It also confirms to the visitor in their language and runs the form's `afterSubmit()`. `forms.submit` is for visitors and is not an agent tool; `forms.update_submission`, `forms.delete_submission` and `forms.purge` are. No IP address is stored. Spam protection belongs to the front end: Spatie's honeypot and timing check, and Laravel's rate limiter.

**Consequences:** Submissions are module system tables (Eloquent), not pages. The admin inbox, the Livewire component and MCP read tools come in the next steps. Removing a field from a form keeps its stored answers, which the snapshot still labels.

## ADR-0053: Form builder: forms are pages, fields are matrix blocks

**Status:** Accepted

**Context:** Code forms (ADR-0052) need a developer for every form. Editors should make simple and multi-step forms themselves, without a second pipeline: what a builder form sends must reach the same inbox, mail and agents.

**Decision:** Builder forms are pages using the `form` template under a hidden, system `/_forms/` page, installed by `forms.install` the first time `forms.create` runs, as menus do. Their fields are blocks of the `form_fields` matrix: one block type per field kind, plus `step` (starts a step; blocks before the first one make step 1) and `group` (takes the blocks indented under it, `max_depth: 1`). Block settings are `form_*` fields: label and help translatable, name optional (made from the default-language label), required, width, options (the new `options` field type: stable values, labels per language), maximums, file limits, and a condition (another field's name and values). `PageFormCompiler` turns a published form page into a `PageForm`, a `Form` like any code form, compiled once per page revision. `FormRegistry` serves code forms first, then builder forms by page name. An after hook on `pages.save` refuses a form page that would make a broken form, on the block that is wrong, and the save rolls back. The cases are names repeated or unusable, choices without options, conditions on fields that do not come before, indented blocks outside a group, groups or steps in a group, a published form without fields, and a name a code form uses. Templates pick a form with the `form` page field, limited to the `form` template, and render it with `<x-lynx-form :form="$page->form" />`. `forms.manage` covers the page actions inside `/_forms/`.

**Consequences:** Builder forms get drafts, revisions, activity and content packages from pages for free, and agents build them with `forms_create` and `pages_save`. Groups cannot nest. Unpublished forms are not served, and the picker can still choose them. Editing a published form changes it at once; submissions keep their own snapshot.

## ADR-0054: A search index per page and language, with database full text

**Status:** Accepted

**Context:** Editors and visitors need to find pages by any text they hold, not only the title: a sentence in a matrix block, a choice label, a description. Selectors compare one field at a time with `LIKE`, which cannot look inside item fields cheaply, ignores accents only by chance, and scans every field table.

**Decision:** Each template chooses its searchable fields with a `searchable` setting on the template field, like `required`; the title is always indexed. `lynx_search` holds one row per page and language with the text of those fields in that language, read with fallback, tags stripped, lowercased and accents folded. Field types give their text through `ProvidesSearchText`: text and textarea their words, choices their labels in the language, repeaters and matrices the text of their enabled items' fields. Types without it add nothing. The row is derived data, like a cache: a listener rebuilds a page's rows after `PageSaved`, `PageRestored`, `PageTemplateSwitched` and `TemplateUpdated` (for every page on that template), drops them after `PageDeleted` and `LanguageDeleted`, and fills every page after `LanguageCreated`. `search.rebuild` (and `lynx:search-index`) rebuilds everything, after field settings change. The engine follows the connection: SQLite uses an FTS5 table, PostgreSQL a generated `tsvector` with a GIN index (the `simple` configuration, so no stemming), other drivers `LIKE`. Selectors gain `search*=words` (also `%=`): pages whose index in the language being read holds every word, each as a word prefix. The admin's search box uses it. The index is derived data, not page data, so it is written outside actions, and the SQLite engine builds its MATCH SQL from folded words, which hold only letters and digits.

**Consequences:** Search is accent and case blind and matches word beginnings (`estaç` finds `estações`), but not word endings or stems, so the admin search no longer finds `lph` in Alpha. Changing a field's settings (a choice label, a matrix type's fields) needs `lynx:search-index`. Drafts are not indexed until published. `search` is a reserved field name.

## ADR-0055: SEO is a module that reads pages, with opt in fields per template

**Status:** Accepted

**Context:** Pages need a title pattern, a description, a share image, a canonical link, a robots rule, social cards and hreflang links, and a site needs `robots.txt`. ADR-0008 already places global SEO on a settings page and ADR-0017 names a `SeoResolver`. Adding SEO fields to every template would clutter templates that never reach search engines, and a separate SEO table would need its own languages, editor and API.

**Decision:** `lynxcms/seo` is a module. Site settings (site name, title pattern, default description and image, X account, extra robots lines) live on a hidden system page of the singleton template `seo-settings`, edited in the page editor; `seo.manage` covers the page actions there. Templates opt in through `seo.enable_template`, which adds `seo_title`, `seo_description`, `seo_image` (with `lynxcms/media`), `seo_noindex` and `seo_canonical`; `seo.disable_template` removes them and keeps the values as orphans. `SeoResolver` builds a `SeoData` per page in the current language from the page's own fields, then its content (`description_fields`, `image_fields`), then the settings, so pages without SEO fields still get good tags. `<x-lynx-seo>` and `@seo` render it. Pages say noindex when marked, when hidden, and outside production. `/robots.txt` is a route; other modules add lines through `RobotsTxt::line()`. The admin gets a Templates tab, an audit and a preview linked from the page editor through `EditorLinks`. Agents get the actions and the `seo_inspect` read tool.

**Consequences:** SEO stays out of the core and out of templates that do not need it. Values are read at render time, so a changed title pattern or default applies everywhere at once. Staging sites stay out of search by default; production is detected from `APP_ENV`, so a production site with the wrong environment would say noindex everywhere. JSON-LD and the sitemap build on this module in later steps.

## ADR-0056: Structured data maps templates to schema.org types on the settings page

**Status:** Accepted

**Context:** Rich results need JSON-LD: events with their venue, shops with opening hours, products with prices, questions and answers. Pages hold this data in their own fields, named as each site likes. Writing JSON-LD in every template's view repeats the same work and breaks when fields change; a raw JSON field per page leaves editors to write JSON by hand.

**Decision:** Every page gets a JSON-LD `@graph` with `WebSite`, the site's `Organization` or `Person` (from the SEO settings), `WebPage` and `BreadcrumbList`. A template can be mapped to one type from a fixed catalogue (`SchemaTypes`: articles, Event, Product, Course, Place, local businesses, Person, FAQPage), each with its properties and the kind of value each takes. Mappings are items of a matrix field on the SEO settings page: template, type, and `property: source` lines, where a source is a field, a built in (`@title`, `@url`, `@published`, `@modified`) or a constant (`=EUR`). Dotted properties build nested nodes; a page reference to a page whose template is mapped embeds that page's node once and links it by `@id` (two levels at most); a list property reads a repeater or matrix, one node per enabled item, its sub-properties reading the item's subfields. Values are read in the current language with fallback and converted by kind. An after hook on `pages.save` refuses mappings with unknown templates, types, properties or fields, or fields of the wrong kind, on the item's path. The admin edits mappings on a Structured data tab with suggested fields. `JsonLd::extend()` lets code change the graph.

**Consequences:** Editors and agents describe content once per template, and changes to the fields are caught when the mapping is saved, though not when a field is later removed from the template (the property is then left out). The catalogue limits what can be described without code; types outside it go through `JsonLd::extend()`. The sitemap takes ADR-0057.

## ADR-0057: The sitemap is built on request, cached, and split by pages per part

**Status:** Accepted

**Context:** Search engines need a list of every page in every language, with alternates. The protocol caps a file at 50000 URLs, and large sites want one sitemap per kind of page to see indexing per template. Writing files to `public/` needs a writable disk and a queue or schedule to keep them current.

**Decision:** `lynxcms/sitemap` serves `/sitemap.xml` and its parts as routes. It lists published pages of templates with URLs (hidden ones only when configured, minus excluded templates), one `<url>` per language the page has a URL in, with `xhtml:link` alternates and `x-default`, and `lastmod` from the page's last change. The limit of URLs per file comes from the admin's settings page, else `lynx-sitemap.per_file`, clamped to 50000. A part holds `limit / active languages` pages, so parts are found by offset in page id order without reading the pages before them. With `group_by: none` the root is a urlset when one part holds everything, else an index of `/sitemap-{n}.xml`; with `group_by: template` it is always an index of `/sitemap-{template}-{n}.xml` for templates that have pages. Built files are cached under a version that any `ContentChanged` event replaces, and under a fingerprint of the settings, so config changes never serve old files; responses carry an ETag. `SitemapFilters::exclude()` lets modules veto pages per language; with `lynxcms/seo`, pages marked noindex or whose canonical URL is another page are left out and `robots.txt` names the sitemap.

**Consequences:** No files, no cron. Parts can hold fewer URLs than the limit when filters drop pages or pages lack some languages, never more. The index has no `lastmod`, since that would mean reading every part to build it. Every content change rebuilds on the next request, which is cheap per part; a site with frequent writes and many parts can warm the cache with `lynx:sitemap --warm`.

## ADR-0058: Page tables hold pages made for one page

**Status:** Accepted

**Context:** Some content is a list of things with many fields of their own: a course's sessions, an event's speakers, a product's variants. Repeaters and matrices keep items inside the page, which suits short items but not ones that need their own URL, revisions, files or permissions. ProcessWire's Page Table answers this with real pages listed in a table on the parent's editor.

**Decision:** A `page_table` field type stores ordered page ids like the page reference type, through a shared `StoresPageReferences` trait, so templates read a `PageArray`, selectors compare ids and backlinks, export and import work unchanged. Settings name the item `templates` (required, enforced on save), the `parent` path new items go under (default the page itself), the admin `columns`, and what `remove` does: `keep` (default) or `trash`. In the admin a nested Livewire table creates an item with `pages.save` from its title, hidden from navigation, and adds it to the list; rows link to the page editor, move up or down, and are removed from the list. The list is saved with the page. The type needs a saved page, since new items live under it.

**Consequences:** Items are full pages: their own revisions, drafts, files and permissions. Created items exist as soon as they are added, even if the page is not saved, and pages removed with `keep` stay as hidden pages under the parent, found in the tree. Agents create items with `pages_save` and set the list like any page reference.


## ADR-0059: Covering indexes and index-friendly selector clauses

**Status:** Accepted. Amends ADR-0023.

**Context:** Comparing ProcessWire's selector engine with ours showed that exact and range clauses on translatable fields compared a `COALESCE` over the language joins, which no index can serve, and that SQLite answered shared field clauses by probing every page of the template. The `lynx:bench` run on 50,021 pages (chain `l1 → pt → default`, median of 7 runs, including loading the pages, on a laptop) gave:

| Case | SQLite before | SQLite after | PostgreSQL before | PostgreSQL after |
| --- | --- | --- | --- | --- |
| Listing (`template=course`) | 7.8 ms | 1.0 ms | 10.7 ms | 4.0 ms |
| Shared filter (`price<200`) | 15.7 ms | 7.5 ms | 13.5 ms | 15.8 ms |
| Shared equality (`price=500`) | 15.8 ms | 1.2 ms | 4.7 ms | 4.5 ms |
| Translatable contains (`title%=`) | 45.7 ms | 1.1 ms | 83.2 ms | 77.9 ms |
| Translatable equality | 36.9 ms | 0.3 ms | 56.1 ms | 2.3 ms |
| Translatable range | 37.7 ms | 1.8 ms | 63.0 ms | 21.1 ms |
| Translatable sort | 39.5 ms | 39.3 ms | 66.7 ms | 70.8 ms |
| Count (`price>100`, half the pages) | 15.5 ms | 23.4 ms | 13.9 ms | 13.6 ms |

**Decision:** Field tables index their value together with the page id (`FieldSchemaManager::indexes`): `(data, page_id)` for shared fields and `(language_id, data, page_id)` for translatable ones, which replaces the index on the language alone. `=`, `<`, `>`, `<=` and `>=` with non-empty values on a translatable field compile to `id IN` the union of one lookup per language in the chain, each taking the pages whose value in that language matches and that have no value in an earlier language (`TranslatableMatch`); results are the same as comparing the effective value. Empty values, `!=` and the LIKE operators keep the `COALESCE`. Shared field clauses compile to `id IN (pages with a matching row)` instead of `EXISTS`, which PostgreSQL plans the same way and SQLite can start from the index. Pages are indexed by `(template_id, sort, id)` instead of `template_id`, so listings in the default order read their first rows from the index. Migrations move existing tables onto the new indexes.

**Consequences:** Exact lookups and listings no longer grow with the number of pages. Counting a clause that matches most pages is slower on SQLite, since it builds the list of matching ids first. Sorting by a translatable field, and `%=` on PostgreSQL, still read every candidate; trigram indexes or the search index (ADR-0054) remain the way to speed up contains. The benchmark has cases for listings, exact and range clauses.

## ADR-0060: Selectors match several fields, all words, and counts

**Status:** Accepted. Extends ADR-0010.

**Context:** Compared with ProcessWire, our selectors could not look in several fields at once (`title|summary%=risk`), had no operator for words in any order (ProcessWire's `~=`), and counted only repeater and file items (`credits.count>2`), not children or page references.

**Decision:** A clause may name several fields separated by `|`. The page matches when any of them matches; with `!=` it matches when none of them holds the value. Each field compiles as it would alone, so fallback, subfields and indexes apply per field. `sort`, `limit`, `start`, `include`, `language` and `search` cannot be combined. `~=` matches text containing every word of the value, in any order and any case, as one `LIKE` per word; `search~=` is a synonym of `search*=`, which already needs every word. `children.count` compares the number of child pages (drafts and system pages left out, hidden and unpublished ones counted, as ProcessWire's `numChildren` does), and `{field}.count` the number of values of a shared field holding several (page references, choices with several values), next to the item counts of repeaters, matrices, images and files.

**Consequences:** Clauses over several fields cost one condition per field, joined with OR, so they read every candidate on large templates. `~=` on large text fields scans like `%=`; the search index stays the fast way to find words. Counts are correlated subqueries, served by the `(parent_id, sort)` index and each field table's primary key.

## ADR-0061: Sort keys for translatable fields, and trigram indexes on PostgreSQL

**Status:** Accepted. Amends ADR-0023 and ADR-0059.

**Context:** After ADR-0059, two selector cases still read every candidate row: sorting by a translatable field (the value with fallback is a `COALESCE`, which no index can order), and the contains operators (`%=`, `*=`, `~=`, `^=`, `$=`), whose leading wildcard no B-tree index serves. A–Z listings and text filters on large templates pay for it.

**Decision:** `lynx_sort_keys` holds, for each page, field and language, the value visitors see in that language (fallback included, cut to 191 characters), for the translatable fields named in `lynx.sort_keys` (default `title`). Sorting by such a field reads it through an index on `(field_id, language_id, value, page_id)`. The keys are derived data, like the search index (ADR-0054): rebuilt for a page after `PageSaved`, `PageRestored` and `PageTemplateSwitched`, for a template after `TemplateUpdated`, for every page after `LanguageCreated`, `LanguageDeleted` and a `LanguageUpdated` that changed the fallback, dropped after `PageDeleted`, and rebuilt by `lynx:sort-keys`. Values are never copied between languages in the field tables (ADR-0005). On PostgreSQL, `lynx.trigram` (env `LYNX_TRIGRAM`) adds GIN trigram indexes on `lower(data)` to the tables of text fields: `lynx:trigram` installs `pg_trgm` and indexes existing fields, and new fields get the index. With it on, the contains operators on translatable fields use the per-language lookup of ADR-0059, so the index serves them too.

Every page has a key row per configured field and language (null when nothing shows), so sorting joins the keys and PostgreSQL reads them in index order; a field sorts through its keys only once a full rebuild has written them (recorded in the `sort_keys.fields` setting), so a field added to the configuration keeps the `COALESCE` until `lynx:sort-keys` runs. The status filter is written as `status IN (allowed values)` instead of `status & excluded = 0`, which PostgreSQL cannot estimate and planned as a few hundred rows. `languages.update` now dispatches `LanguageUpdated`, which it never did, with the columns that changed; the sort keys and the search index (whose rows also hold text with fallback, ADR-0054) are rebuilt when the fallback changed, and not for a new name or label.

Measured with `lynx:bench` on the 50,021 pages of ADR-0059 (median ms; "before" is after ADR-0059):

| Case | SQLite | PostgreSQL before | PostgreSQL after | with trigram |
| --- | ---: | ---: | ---: | ---: |
| Translatable sort | 27 (was 39) | 71 | 4.8 | 4.6 |
| Translatable sort, page at offset 5,000 | 46 (was 56) | 123 | 9.2 | 8.7 |
| Shared filter (`price<200`) | 7 | 16 | 5.8 | 4.5 |
| Contains, common words (`title%=course 1`) | 1.2 | 78 | 3.1 | 26.1 |
| Contains, rare words (`title%=course 4242`) | 45 | about 90 | 89.7 | 5.6 |

A database view was considered instead. A plain view only names the `COALESCE`, which cannot be indexed, so sorting costs the same. SQLite has no materialized views, and PostgreSQL refreshes a materialized view as a whole, so it would either recompute every page on each save or serve stale order between refreshes; the fallback chains, whose length differs per language, would also need a recursive query, rebuilt with every change to `lynx.sort_keys` or the languages. The table is a materialized view kept current page by page, the same pattern as the search index on both databases. Triggers inside PostgreSQL would not depend on events, but field tables are created at run time and SQLite would need its own triggers.

**Consequences:** Sorting by a configured field costs one indexed join, and ties beyond 191 characters fall back to the page order. Changing `lynx.sort_keys` or a field's fallback setting needs `lynx:sort-keys`. SQLite still starts from the pages table, so its sorts improve less. Trigram indexes make rare words fast and common words slower, cost writes, and need the extension, so they stay opt-in; patterns under three characters cannot use them.

## ADR-0062: Page gates and front-end visibility rules

**Status:** Accepted.

**Context:** Lynx renders every published page to anyone, and selectors return every published page. Modules such as page passwords (see `documentation/plans/page-passwords.md`) need to stop some visitors from opening a page, and to leave the pages they cannot open out of listings, without the core knowing why.

**Decision:** Two `@api` registries in core. `PageGates` holds closures `fn (Page, Request): ?Response` that `PageController` asks before rendering a page; the first response is sent instead of the page. `PageVisibility` holds `VisibilityRule`s, each with `apply(Builder)` to narrow a selector query and `allows(Page)` to judge one page. While rules are active, `PageFinder` applies them to every compiled selector (finds, counts, ids, children), and page reference fields drop the pages a rule refuses. `lynx()->pages->get()` and the page loader are not filtered, so code can still reach known pages such as settings pages. Rules are active during HTTP requests, front-end Livewire updates included, and are switched off for the rest of a request by the `WithoutPageVisibility` middleware, which the admin's routes (and, through Livewire's persistent middleware, their components' updates) and the MCP web route carry; and inside `PageVisibility::withoutRules()`. They never apply in console processes (Artisan, queue workers, the scheduler), except under the test suite, which runs in the console too. With no rules registered nothing changes.

**Consequences:** Exports, backups, the sitemap and search builders, MCP and agents see every page; visitors only what the rules allow. An app route that lists pages for guests is filtered without doing anything; one that must see everything wraps its work in `withoutRules()` or uses the middleware. Rules must keep `apply()` and `allows()` in agreement, since listings use one and single pages the other.

## ADR-0063: How a field shows in the page editor: locked, hidden, collapsed, width, placeholder and icon

**Status:** Accepted. Extends ADR-0030 and the template layout (tabs, sections and columns).

**Context:** ProcessWire lets each field of a template choose how it appears in the page editor (`Inputfield::collapsed`): open, collapsed, collapsed when blank or when populated, locked (the value shows but cannot be edited, only the API writes it), or hidden; and how wide it is (`columnWidth`), with a placeholder and an icon. Lynx fields were always open and editable when their type has an input, and full width.

**Decision:** A template's field takes four more settings, kept with `required` and `show_if` on the template field. `editor` is one of `open` (default), `collapsed`, `collapsed_blank`, `collapsed_populated`, `locked` and `hidden` (`FieldDisplay`). Locked fields show their value read only, through the type's display view; hidden fields are left out of the editor; both are left out of what the editor loads into its form and sends to `pages.save`, so a crafted request cannot change them from the editor. Code, imports, actions, agents and MCP still write them, as ProcessWire's API does. A locked or hidden field cannot be `required` or have `required_if`, since editors could not fill it; conditions that read one see its saved value. Collapsed fields show their label as a button and open on errors. `width` (25, 33, 50, 66, 75 or 100, the default) places fields side by side in a card when it is wide enough; a field narrower than the card puts its label above the input. `placeholder` is translatable text shown in empty inputs, after a value falling back from another language. `icon` is a Lucide icon name shown before the label.

**Consequences:** Fields only some code should change can live on the template without editors touching them. Widths are a hint: sidebars and narrow screens stack fields. Per-field roles (ProcessWire's view and edit roles) remain a possible later step.

## ADR-0064: Page cache, fragment cache and islands

**Status:** Accepted. Plan: `documentation/plans/page-cache.md`.

**Context:** Every front-end page goes through PHP: the session starts, the path is looked up, the page loads and Blade renders. Demanding sites expect ProcessWire ProCache speed, where guest pages are stored HTML that the web server returns without PHP, and ProcessWire's markup cache for parts of pages. spatie/laravel-responsecache stores responses in the Laravel cache, but every hit still boots the framework and starts a session, and what to clear when content changes is Lynx specific anyway. Laravel Cloud gives no access to the web server and runs replicas with an ephemeral disk, so files on one replica cannot be cleared from another.

**Decision:** A module, `lynxcms/cache`. Pages are stored through a `PageStore` with two drivers: `files` (HTML on local disk, served by nginx, Apache, Caddy or a pre-boot `serve.php`) and `store` (a Laravel cache store shared by all replicas, served by a middleware that runs before the session). Whether a page may be stored is decided on the final response, after `RequestHandled` listeners: only guest, 200, HTML responses without `Cache-Control: no-store`, no cookie other than the session's, no CSRF token in the body and a canonical path. Any `ContentChanged` clears every page unless the template's rule narrows it to the page, its parents, children, siblings, the home page or chosen pages. A revision counter, changed by every clear, guards against storing a page rendered during one. Query strings are cached only for parameters a template rule allows, sorted, after dropping tracking parameters. The fragment cache keeps an index of stored fragments (name, language, audience, what they depend on), so the admin can list them and a content change clears exactly the fragments depending on it, without cache tags; signed in users always get a fresh render. Pages with forms are stored with token refresh by default (a placeholder in the stored page and a script that fetches a fresh token after load, which works on every serving tier); a cached form loses the honeypot's timing check, so forms that rely on it belong in islands, and `csrf: skip` leaves such pages out. Islands (`<x-lynx-island>`) print an encrypted placeholder on pages that may be stored; the refresh script fills them and the token from one `GET /lynx-cache/dynamic` request, and a Livewire island forces Livewire's assets onto the stored page; its components' `@assets` come back with the islands and are added to `<head>` before them, and their `@script` blocks run from the components' own effects. Livewire's back button `no-store` headers are recognised and ignored; any other `no-store` keeps a page out. On Laravel 13, same-origin requests from secure contexts pass CSRF checks by their `Sec-Fetch-Site` header; elsewhere the token decides, and since a visitor's first requests can each start a session, the refresh script sends the `XSRF-TOKEN` cookie (which always matches the session cookie) instead of tokens copied into the page.

**Consequences:** A single server gets static file speed; Laravel Cloud and multi-server setups get one cache read per hit. Clearing everything is the safe default and costs a cold cache after each save, which template rules can reduce. Deploys that change templates must clear the cache. An optional `edge_ttl` lets the Cloudflare edge keep pages for a few seconds, accepting that much staleness, because Laravel Cloud has no per URL purge.

## ADR-0065: Page URLs without a trailing slash by default

**Status:** Accepted. Changes the default of `lynx.trailing_slash`, which followed ProcessWire.

**Context:** Lynx printed page URLs with a trailing slash (`/courses/risk/`), as ProcessWire does: pages form a tree, and a slash reads as "a folder with pages below it". Laravel writes URLs without one, Lynx's own admin included (`/lynx/pages`), so a Lynx site mixed both styles. Laravel's stock Apache `.htaccess` also redirects every address ending with a slash to the one without it, so on Apache each click on a Lynx link cost an extra redirect, and the canonical URL the page declared was the one the server redirected away from. Testing the page cache on a real Apache server showed it.

**Decision:** `lynx.trailing_slash` defaults to `false`: page URLs have no trailing slash (`/courses/risk`), the home page stays `/`, and a language's home is `/pt`. `PageUrl::of()` is the only place that reads the setting; everything that prints a page URL goes through it (links, menus, hreflang, the sitemap, canonical URLs, redirects for old paths and for the default language's prefix). Both forms keep opening the page. Setting it to `true` restores the slash, for sites migrated from ProcessWire that keep their addresses. Content package keys keep their trailing slash: they are tree paths, the same on every site whatever the setting.

**Consequences:** Laravel's stock web server rules fit Lynx as they are. Sites that set `true` on Apache remove Laravel's "Redirect Trailing Slashes" rule (`lynx:page-cache:server apache` says so). The change came before v1, so no live site's URLs change. Lynx still answers both forms without redirecting between them; the canonical link marks the right one.

## ADR-0066: Menus keep their structure in the Laravel cache

**Status:** Superseded by ADR-0068. Extended ADR-0025.

**Context:** Menus are pages (ADR-0025), and each menu field is its own table, so building a menu took about twenty queries before it showed anything: the menus root, the menu page and its fields, the items and one query per item field. A page with two menus spent about 60 of its 80 queries on them. On SQLite that is invisible; on PostgreSQL each query is a network round trip, and a load test measured renders 2 to 2.5 times slower than on SQLite, menus being the largest share. Menus change rarely and their structure is the same for every visitor. Laravel 13 apps refuse to unserialize objects from the cache by default (`cache.serializable_classes`), so pages cannot be cached as objects.

**Decision:** `lynx()->menus()->get()` reads a menu's structure (its page and item pages) from the default Laravel cache store, stored as plain arrays through a new `@api` `PageSnapshot` (`of()` and `restore()`), and loads it from the database only on a miss. Keys carry a version kept in the same store; clearing raises it, so every replica sharing the store sees it at once, and entries also expire after a day. A `ContentChanged` clears it when any page may have changed (`affectedPages()` null, such as a language change) or when a changed page is the menus root, a menu or an item (a deleted item lists its parent); field and template actions, which fire no event, clear it too. What depends on the request stays live: linked pages are loaded in one batch, then visibility, URLs, child pages, labels in the current language and the active link are resolved on every read. A structure holding an object value is not cached. This needs no `lynxcms/cache`; fragments around `<x-lynx-menu>` still save the rest of the work.

**Consequences:** A request reads each menu in one cache read plus the queries for what the visitor may see (19 instead of 62 for the workbench's two menus). On a database cache store that read is one query. The first request after any menu change, or after a schema change, rebuilds. Code that edits menu pages outside actions (raw SQL) must clear the application cache (`php artisan cache:clear`) or wait a day.

## ADR-0067: Fields load on first read, once per batch

**Status:** Accepted. Changes how `PageLoader` hydrates pages (ADR-0010).

**Context:** `PageLoader` loaded every field of every page it hydrated: one query for the pages, one for their names, then one per field of their templates. A menu showing course titles loaded each course's body, price, related courses and enrolment form; a listing of titles did the same. On SQLite the extra queries cost little; on PostgreSQL each is a round trip, and they also carry the largest values (rich text) for nothing. ProcessWire loads only the page row and its autojoin fields (the title by default), lets a `find` choose more with `join=`, and loads any other field when it is first read, one query per page and field.

**Decision:** Pages load with their row, names and statuses, the title, fields whose `autoload` setting is on ("Load with the page" in the admin) and fields a selector names in `join=` (`join=summary|price`, or `join=all` for every field; `with:` reference fields count as joined). Every other field is deferred. The pages hydrated together share a `DeferredFields` batch: the first read of a deferred field on any of them loads it for every page of the batch still without it, in one query, so a listing never costs one query per page. Every `Page` method that reads or writes a value loads the field first (`get`, `getUnformatted`, `getLanguageValue`, `setLanguageValue`); code that needs all values (`rawValues()`, saving, `valuesByLanguage()`, draft previews through `withValuesOf()`) loads the rest. Copies of a page (`clone`) load their own values. `join` is reserved as a selector keyword and as a field name.

**Consequences:** A listing costs 3 queries plus one per field it actually shows; the workbench pages went from 35, 33 and 44 queries to 29, 28 and 33, and its menus from 19 to 13. A field first read after its page changed in the database shows the newer value, which only matters for code that keeps pages across writes. Templates that read every field of every listed page cost the same as before; `join=all` restores the old loading where that is known in advance.

## ADR-0068: Menus are stored built, per language, and rebuilt when what they show changes

**Status:** Accepted. Supersedes ADR-0066; extends ADR-0025.

**Context:** ADR-0066 kept each menu's items in the Laravel cache, which saved the menu and item queries but still resolved linked pages, visibility, URLs and child pages on every request (13 queries for the workbench's two menus once ADR-0067 deferred fields). Those results are the same for every visitor who sees what everyone sees, in a given language. The cache store also had to hold plain arrays only (`cache.serializable_classes`), lived apart from the database it described, and a cache flush lost it.

**Decision:** A menu is stored built, per language, in `lynx_menu_builds`: its title, items, labels, URLs, child pages and link attributes as JSON, without the active link, which `ActiveTrail` marks per request from the URL. Visitors who see what a visitor with no cookies and no account sees read it with one query; the first such visitor after a change builds and stores it. Others (a visitor who unlocked a password page, an editor a rule lets see more, admin routes where rules are off, console processes) get it built from the pages and nothing is stored. Core gains `VariesByVisitor` (`@api`): a visibility rule says whether the current visitor is that default visitor, and `PageVisibility::seesDefault()` combines them; a rule without it counts as treating every visitor apart. A build records in `lynx_menu_build_pages` the pages it used (the menu, its items, every linked page whether shown or not, the child pages it lists, and the pages above them) and the pages whose children it lists (the menu, its items, linked pages that add children, and listed children). A `ContentChanged` drops the builds that used a changed page or list the children of its parent; one with no page list (a language change) drops all; field and template actions drop all, inside their transaction. Rebuilding is lazy: dropping is cheap and the next visit builds. `menus.rebuild` (one menu or all; an admin **Rebuild** button) drops builds by hand, for changes made outside Lynx.

**Consequences:** A request reads each menu with one query on any database and cache store (2 instead of 13 for the workbench's two menus; the home page 29 queries to 21). The first visit after a change builds the menu (about 50 queries for the workbench's two). Changes near the root drop more than they must (every menu records the home page as an ancestor), which costs one rebuild, never a stale menu. A build computed while a change is being saved can be stored just after that change dropped it; the next change, or `menus.rebuild`, corrects it. Pages edited with SQL need `menus.rebuild`.

## ADR-0069: Static site export renders through the HTTP kernel where the content lives

**Status:** Accepted. Extends ADR-0011 and ADR-0064.

**Context:** Some sites need no PHP at run time: brochures, documentation, campaign pages, hosted on GitHub Pages, Netlify, Cloudflare Pages or a bucket. Statamic's SSG builds them from a git checkout because its content is files. Lynx keeps content in a database, so an export can only run where that database is. Interactivity then comes from Alpine or plain JavaScript; Livewire, forms and islands need PHP.

**Decision:** A module, `lynxcms/static-site`, exports the site with `static.export` (and so its MCP and AI tools), `lynx:static` and an admin screen. It opens every address through the HTTP kernel as a visitor, as the page cache warmer does, so the export holds exactly what the site sends: published pages of templates with URLs, hidden ones included, found with visibility rules on (password pages are left out), in every active language they are live in (core gains `PageUrls`, `@api`, which the warmer now uses too). Each page is written as `{path}/index.html`; responses that are not 200 or are marked `no-store` are left out. Rendering happens at the static address (`app.url` and the URL generator's root), with the array session driver and, by default, in production without debug, so an export made on a local or staging install is not marked noindex. The addresses in `lynx-static.paths` (the sitemap and robots.txt; a sitemap index brings its parts) and a `404.html` follow, then `public/` is copied, links followed, without PHP entry points and the page cache's files, and scripts, styles and icons pages load from a route (Livewire's script) are fetched and written. Pages Livewire marks `no-store` only for the back button are exported, as the page cache stores them. Old addresses (ADR-0011) become a `_redirects` file and a refresh page at each old address with the new one as canonical, for hosts that read no such file. Pages with a form that posts, Livewire, islands or links with a query string are exported and reported. The folder and address come from config, never from action input; the command may override them. The export is built in a staging folder beside the target and moved into place by a listener of `StaticSiteBuilt`, which the action queues and Lynx dispatches only after commit, so a halted export keeps the last one; a folder is replaced only if an export made it (`.lynx-static`) or it is empty.

**Consequences:** A static site is a copy of what the live site sends, with no second renderer to keep in step. It must be exported again after changes, by hand, from the admin, or on a schedule (scheduled publishing included); exporting only what changed is left for later. Query strings are lost, so paginated listings export their first page until Lynx has path based pagination. The export takes about as long as rendering each page once (20 to 50 ms each on the test VMs) and runs inside one action, so very large sites should export from the command line.

## ADR-0070: Template values are safe to test: empty texts are null, single references are pages

**Status:** Accepted. Amends ADR-0031 and ADR-0042.ADR-0070

**Context:** Rebuilding a real site on Lynx (a demo rebuilt from an existing Statamic site) showed four traps in what templates read. Shared and translatable texts are value objects (ADR-0031), which PHP always treats as true, so `@if ($block->sublabel)` printed empty markup and `$block->utm_source ?: $page->utm_source_global` never fell back. A page reference with `multiple: false` still read as a `PageArray` (ADR-0042), so templates needed `->first()`. Joining titles with `collect($pages)->map(...)->implode(', ')` returned empty strings, because Laravel's `Collection::implode()` checks for its own `Stringable` class, not PHP's interface, and plucks the glue from each value. And `$image->size(300, 300)->webp()` made a WebP copy of the PNG copy, named with the size twice, leaving the PNG behind.

**Decision:** A shared text with no value reads as `null`; a translatable text reads as `null` when no language holds a value, and stays a `TranslatableValue` otherwise, so `in()` and fallback still work. A page reference that holds one page reads as that page or `null`; `phpType()` says so for the PHPStan stub. Repeater and matrix items read their subfields the same way. `PageArray` gains `implode($glue, $property)`, like ProcessWire's, which casts each value to a string, and `filter()`, which returns a `PageArray`. Image variations remember the original and their settings: a chained call starts again from the original, and the file is written when `file()` or `url()` is first asked for, so `size(300, 300, 75)->webp()` is one file, `photo.300x300-q75.webp`.

**Consequences:** `@if`, `?:` and `??` behave as template authors expect, and single references need no `->first()`. Code that called `PageArray` methods on a single reference, or on an empty text value, must change: menus, the default page view and their tests are updated. A filled text is still an object, so strict comparisons keep needing a cast or `getUnformatted()`, as ADR-0031 says. A translatable text empty in the current language but filled in another stays truthy. Variations are no longer written by `size()` alone, only when used; `media.prune` keeps variations of kept files as before.

## ADR-0071: The admin's built assets are made when the package is split, not committed

**Status:** Accepted

**Context:** The admin serves a prebuilt stylesheet and script from `packages/admin/dist`, so a host app needs no Node and no build step. One Tailwind build scans the views of every first-party package with admin screens (admin, menus, media, forms, SEO, sitemap, page cache, static export) and writes a single minified line. The file was committed, because the split repositories are made with `git subtree split` and only carry what is in git. Any two branches that added a class anywhere changed that one line, so they always conflicted, and every rebase meant rebuilding it by hand.

**Decision:** `packages/admin/dist` is not committed in the monorepo. The split workflow builds it from the full monorepo (it needs every package's views) and commits it on top of the admin split before pushing, so `LynxCMS/admin` and its tags still carry `dist/` and Composer installs work as before. CI builds the assets for the test jobs, which serve them, and so checks that they build. Developers run `composer assets` (after `npm --prefix packages/admin ci` once). Without built assets the admin still renders, and the asset routes answer 404 with the command to run.

**Consequences:** Branches no longer conflict on generated files. Each push to the admin split adds a build commit, so the split's history differs from the monorepo's by one commit per push; the split was already force pushed. Working on the monorepo, or against a local checkout through a path repository, needs Node for the admin's assets. Modules outside the monorepo (paid modules in private repositories, ADR-0018) are still not scanned by this build; they will get an API to ship their own stylesheet when the first one needs it.

## ADR-0072: The documentation is a static VitePress site in the monorepo, served at lynxcms.org/cms/docs

**Status:** Accepted

**Context:** The documentation's Markdown lived in the monorepo's `docs/`, and a separate repository, `LynxCMS/docs-site`, served it with Laradocs, a Laravel app that renders pages and searches them in PHP. That needed a PHP server, kept the site's code apart from the docs it renders, and gave pull requests no preview. The docs were also written as notes for people who knew ProcessWire, not for the Laravel developers Lynx is for.

**Decision:** The site is built with VitePress from `docs/`, with its configuration in `docs/.vitepress`. It is served at `lynxcms.org/cms/docs` by a Cloudflare Worker with static assets and no code of its own (`docs/wrangler.jsonc`), on a route that leaves the rest of `lynxcms.org` alone. `.github/workflows/docs.yml` builds it on every change (the build fails on dead links), uploads a preview for each pull request, and deploys from `main`. Pages follow Laravel's documentation in structure and tone, for developers who know basic Laravel. Code a tutorial shows lives in `docs/snippets` and runs in the test suite, and `tests/Feature/Docs` checks that documented selectors parse, internal anchors exist, every field type and Artisan command is documented. `LynxCMS/docs-site` is archived.

**Consequences:** Docs and code change in the same pull request, and reviewers can open a preview. The site needs no server of our own and no PHP. Building it needs Node, in CI and for a local preview (`npm --prefix docs run dev`). Deploying needs a Cloudflare API token in the repository's secrets. Moving the site off Cloudflare means changing the route and the deploy step, not the content.

## ADR-0073: People who sign up get a guest role, so a sign-up never has full rights

**Status:** Accepted (amends ADR-0033)

**Context:** ADR-0033 keeps full rights for every signed in user until any user holds a role, so an install or upgrade never locks anyone out. Sites that let visitors sign up (a members area, bookings, surveys) break that promise's premise: on a site where the owner never ran `lynx:install --user`, the first person to sign up would open the admin with every right.

**Decision:** Install creates a `guest` role with no grants: it sees the website like any visitor and cannot open the admin, which needs `admin.access`. Core listens to Laravel's `Registered` event, which the app's registration (Fortify, Breeze or its own code) dispatches, and gives the new user the role named by `lynx.permissions.registration_role` (`LYNX_REGISTRATION_ROLE`, default `guest`; null gives none). It assigns through `roles.assign`, run as the system, so the activity log records it. Users who already hold a role keep their roles. When the role does not exist, nothing is assigned and a warning says how to fix it. The guest role is a schema item like any role and exports with the schema files.

**Consequences:** The first sign-up turns role enforcement on. If no one is a superuser yet, the owner loses the open rights at that moment; a warning in the log says to run `php artisan lynx:install --user=ID`, which works from the console because the console always has every right. Sites without registration see no change apart from one more role. Apps can give sign-ups a role with grants of their own (such as a reviewer) through the same setting.
