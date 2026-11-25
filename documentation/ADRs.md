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

**Status:** Accepted (PHP template classes dropped by ADR-0133)

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

**Status:** Accepted (the open rights until a role exists are superseded by ADR-0078)

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

**Status:** Superseded by ADR-0127 (amended ADR-0033)

**Context:** ADR-0033 keeps full rights for every signed in user until any user holds a role, so an install or upgrade never locks anyone out. Sites that let visitors sign up (a members area, bookings, surveys) break that promise's premise: on a site where the owner never ran `lynx:install --user`, the first person to sign up would open the admin with every right.

**Decision:** Install creates a `guest` role with no grants: it sees the website like any visitor and cannot open the admin, which needs `admin.access`. Core listens to Laravel's `Registered` event, which the app's registration (Fortify, Breeze or its own code) dispatches, and gives the new user the role named by `lynx.permissions.registration_role` (`LYNX_REGISTRATION_ROLE`, default `guest`; null gives none). It assigns through `roles.assign`, run as the system, so the activity log records it. Users who already hold a role keep their roles. When the role does not exist, nothing is assigned and a warning says how to fix it. The guest role is a schema item like any role and exports with the schema files.

**Consequences:** The first sign-up turns role enforcement on. If no one is a superuser yet, the owner loses the open rights at that moment; a warning in the log says to run `php artisan lynx:install --user=ID`, which works from the console because the console always has every right. Sites without registration see no change apart from one more role. Apps can give sign-ups a role with grants of their own (such as a reviewer) through the same setting.

## ADR-0074: When the app signs users in, Lynx keeps invitations and the account screen

**Status:** Accepted (amends ADR-0041)

**Context:** ADR-0041 gives the admin its own Fortify sign-in under its prefix, and steps aside when the app configures Fortify itself (`config/fortify.php`). Sites with a members area must do that, since Fortify serves one set of screens and visitors need registration at the site's root. Stepping aside also dropped staff invitations and the account security screen, so such a site had no way to bring staff in except letting them sign up and promoting them by hand.

**Decision:** `AdminAuth::mode()` names who signs users in: `lynx` (Lynx's screens), `app` (the app's Fortify; chosen by `auto` when `config/fortify.php` exists, or set explicitly) or `off` (`false`: the app's own sign-in, such as single sign-on). Invitations (the `users.invite` and `users.resend_invitation` actions, the invitation screen and its password broker), the account security screen and the two-factor and passkey columns work in `lynx` and `app`. Two-factor authentication and passkeys appear on the account screen only when the Fortify features that ask for them at sign-in are on, so the screen never offers a second factor the sign-in ignores.

**Consequences:** One sign-in for staff and members, with roles deciding who opens the admin (ADR-0073 gives sign-ups the guest role). Staff onboarding is the same on every site. A site that sets `false` manages accounts entirely itself, as before.

## ADR-0075: System pages are outside the site's family rules

**Status:** Accepted (amends ADR-0032)

**Context:** Family rules (ADR-0032) let a site say which templates may sit under which. Modules keep their content in hidden branches under the home page, such as menus under `/_menus/`, created with the `system` status. A site that limited the home page's children to its own templates could no longer install those modules: the module's root page was refused.

**Decision:** A page created with the `system` status is not checked against family rules. System pages are already outside the content tree in every other way: the admin's tree hides them, selectors leave them out, and they cannot be moved.

**Consequences:** Sites can limit the home page's children freely. Code that creates pages with the `system` status takes on the job of placing them sensibly; only modules do that.

## ADR-0076: Forms that members answer once each

**Status:** Accepted

**Context:** A course survey for members must be answered by people who took the course, once each. Submissions record their account since 0.1.10, but any visitor could answer any form any number of times.

**Decision:** A form says so with `Form::oncePerUser()`, or with the builder's **Members only, one answer each** toggle (`form_once_per_user` on the form template, added to existing sites by `forms.install`). `forms.submit` refuses such a form from an actor that is not a signed-in user, and refuses a second submission from the same account, under a cache lock per form and account so two answers sent together can't both get through. The form component shows the reason, in the visitor's language, instead of the form, with a sign-in link for guests when the app has a `login` route. Who may answer beyond "signed in" (only people who booked the course) stays with the app: put the form on a page that only those people can open.

**Consequences:** Surveys need no code beyond the toggle. Staff and agents submitting for someone else are refused like guests, since such a form's answers must come from the member. Answers are matched by account, so a member with two accounts can answer twice.

## ADR-0077: Private media fields, served by Lynx under the page's own rules

**Status:** Accepted (extends ADR-0046)

**Context:** Media files live on a public disk (ADR-0046), so their addresses work for anyone, whatever protects the page that holds them: a password, a members-only rule, an unpublished status. Paid course materials and similar files must follow the page.

**Decision:** Media fields get a `private` setting. A private field's files are stored on `lynx-media.private_disk` (default `local`) and their URLs point to `/lynx-media/{page}/{file}`, a route of the media package. It serves a file of a page to staff whose roles let them view the page, and otherwise only when the page is published, no page gate answers instead (ADR-0062) and the visibility rules allow it; a gate's answer becomes the file's answer. Responses are `Cache-Control: private` and `X-Robots-Tag: noindex`. Privacy is a property of the field, so values are unchanged: a presented file learns it from its field's settings, image sizes follow their original, and content packages read from either disk and write through the field (CarriesFiles::mapFiles sets the disk). Changing the setting moves the field's files, found in its own values, in repeater and matrix items and in revisions: they are copied within fields.update, and the originals and their sizes are deleted from the old disk only after the change commits. Pruning and deleting a page's folder cover both disks.

**Consequences:** Public sites lose nothing: public fields keep direct disk URLs. Each private file costs a PHP request, and private files don't work in a static export. Several servers need a private disk they all read. A file referenced by two fields of different privacy (rare: each upload names its field) ends up on the disk of the field changed last.

## ADR-0078: Closed by default: a user without a role has no rights, and a visitor is not the system

**Status:** Accepted (supersedes the open rights of ADR-0033 and the warning of ADR-0073)

**Context:** ADR-0033 gave every signed in user every right until any user held a role, so an install or upgrade never locked anyone out. That turned out to be a hole, not a convenience. Any account of the host app (a customer of a shop, a user created by a seeder, a Socialite callback or an import) could open the admin, change the schema and make itself a superuser through `roles.assign`, so the first person to try kept the site. ADR-0073 closed it only for sign-ups that fire `Registered`. MCP clients and agents without a user had every right too. Separately, a web request with nobody signed in ran actions as the `system` actor, which passes every check, so app code such as a public form or a webhook calling `lynx()->run()` wrote with every right.

**Decision:** A user without a role has no rights, whether or not anyone else holds one; `Authorizer::isEnforced()` is true. `lynx.permissions.open_until_roles` (`LYNX_OPEN_UNTIL_ROLES`, default false) brings the old behaviour back for local work, and never for visitors. A web request with nobody signed in acts as the new `guest` actor (`Actor::guest()`), which holds only actions open to everyone (`languages.set_current`, `forms.submit`); a global middleware marks web requests so this holds in tests too. Queue workers, the scheduler and Artisan stay `cli`. Code that must write for a visitor says so: `lynx()->actingAs(Actor::system(), fn () => ...)`. `lynx:install --user=ID` still makes the first superuser and recovers a site without one; `lynx:install` warns when no one is a superuser, and with debugging on the admin's 403 says how to make one.

**Consequences:** Installing Lynx on an app with users gives none of them rights until the owner runs `lynx:install --user=ID`. An upgraded site where no one held a role locks everyone out of the admin until then; the upgrade guide says so. MCP clients and `lynx:ask` acting for no user need `LYNX_ANONYMOUS_ROLE`, as they already did on sites with roles. App code that wrote from guest requests without `actingAs` is refused with an authorization error, which is the point. The test suite keeps open mode for brevity (its tests act as the site owner without a role); the tests of this decision turn it off.

## ADR-0079: The installer creates the first superuser

**Status:** Accepted (amends ADR-0033 and ADR-0078)

**Context:** Since ADR-0078 a site needs a superuser before anyone can open the admin. `lynx:install --user=ID` was the only way to make one, and it needs an account that already exists, which a new app doesn't have: the docs sent people to Tinker to create a user first.

**Decision:** The admin package adds `lynx:superuser`. It asks for an email; if an account has it, it offers to make that account a superuser, keeping its roles; otherwise it asks for a name and a password (twice, checked against the app's `Password::defaults()`) and creates the account through `LynxUsers` with the new `users.create` action, then gives it the `superuser` role through `lynx:install`'s action, installing Lynx first if needed. `--email`, `--name` and `--password` make it scriptable. `users.create` is superusers only and never an agent tool, and the password is never recorded. `lynx:install` offers `lynx:superuser` when no one is a superuser and it can ask (never under `--no-interaction`), and its `--user` takes an email as well as an id.

**Consequences:** Installing Lynx on a new app is two commands, `migrate` and `lynx:install`, with no account made beforehand. Apps with their own user store keep working through their `LynxUsers` binding. Without the admin package, `lynx:install --user` is still the way, since the core has no user creation of its own.

## ADR-0080: Invitation tokens have their own table
**Status:** Accepted (amends ADR-0041)
**Context:** ADR-0041 gave invitations their own password broker, on the app's password reset table, so they could last days while resets last an hour. One table meant one kind of token: a reset link was accepted by the invitation form for as long as invitations last, and an invitation token by the reset form.
**Decision:** The invitation broker uses `lynx_invitation_tokens`, an admin table shaped like Laravel's reset table. Its migration moves the tokens of pending invitations there, so links already sent keep working. When an invited user sets a password through a password reset, their invitation token is deleted along with the pending invitation.
**Consequences:** Each link keeps its own lifetime and opens only its own form. Sites run `lynx:migrate` once.

## ADR-0081: The agent's plan is approved after its transaction ends, and a run has a time limit

**Status:** Accepted (amends ADR-0020)

**Context:** Plan mode runs the agent's actions in one database transaction so later steps can use what earlier ones made (a new page's id), and rolls it back unless the plan is approved. The transaction stayed open for the whole conversation and, with `lynx:ask`, while the person read the plan and decided. On SQLite the first write takes the database's write lock, so every editor, form submission and session write waited or failed until then; on PostgreSQL the rows it wrote stayed locked.

**Decision:** `ask()` rolls the transaction back before calling the confirm callback, and an approval replays the recorded steps through `AgentRun::apply()`, in a short transaction of its own with ids of pages made during planning mapped to the new ones, as code that calls `apply()` later already did. A run has `lynx.ai.plan_seconds` (`LYNX_AI_PLAN_SECONDS`, 120): the session checks it before each tool call, and a run past it throws `PlanTookTooLong`, which rolls everything back. `--apply` keeps committing the run's own transaction.

**Consequences:** No one waits on a person reading a plan, and a slow or stuck model holds writers back for two minutes at most. An approved plan is applied by replaying it, so a step whose arguments name something made earlier in the plan, other than a page id the replay maps, can differ from what the plan showed; the steps the agent tools take (pages by id, path or name, fields and templates by name) replay as planned.

## ADR-0082: A developer's machine downloads missing media from production

**Status:** Accepted (extends ADR-0046 and ADR-0050)

**Context:** Developers start their machine from a content package (ADR-0050), which carries every file its pages name. On a site with a lot of media that package weighs gigabytes, and refreshing it is slow. ProcessWire sites solve this with a hook that downloads a missing file from the live site when it is asked for.

**Decision:** `lynx:export --without-files` writes a package without its `files` folder; an import then keeps the values as production stored them ("42/photo.jpg"). `lynx-media.remote.url` (`LYNX_MEDIA_REMOTE`) is the https address of production's media folder. When it is set and the app is not in production, `RemoteMedia::ensure()` downloads a stored name missing from the public disk, through `RemoteFile`'s checks, before an image size is made from it and when its address reaches Laravel because the web server found no file (a route on the public disk's media path, which answers 404 otherwise). `lynx:media:pull` queues a `FetchRemoteMedia` job for each missing file the pages name, optionally for one branch. Private files are never downloaded: production serves them only to people who may open their page.

**Consequences:** A light package and a remote address give a developer the live site's look without copying all its media. The first render of a page with new images waits for their downloads. Files keep production's folder names, which may be the id of a different page on the developer's machine; that page's folder then holds files it doesn't own, which only matters if the page is deleted. Production never downloads from itself, since the setting is ignored there.

## ADR-0083: Field types reach PHPStan through an extension, not a stub

**Status:** Accepted

**Context:** `lynx:phpstan-stub` wrote a PHPStan stub of `Page` with one `@property` per field. Building the meeting rooms app showed it unusable with PHPStan 2: stub files are validated in a container that only sees stubs and PHP's own classes, so every vendor class the stub named (Carbon, Lynx's value classes) was an unknown class, and those errors cannot be ignored. Two built-in types also lacked a leading backslash, `$page->name` was read only though code sets it, and repeater and matrix items had no types at all.

**Decision:** `lynx:phpstan` writes `lynx/phpstan/types.php`, a PHP array of every field's type, fully qualified, for pages and for items, plus the built-in properties and whether code may set them. `lynxcms/core` ships `phpstan/extension.neon`, which registers `PhpStan\FieldProperties`, a properties class reflection extension that resolves those types with PHPStan's own type resolver, so they may name any class the app loads. Writes accept anything; of the built-ins, only `$page->name` is writable. The extension's classes live in core's source but are only loaded by PHPStan.

**Consequences:** Apps include one file in `phpstan.neon` and commit the generated types next to their schema files. A test runs PHPStan itself on a fixture to check the extension. Upgrading means replacing the stub with the include.

## ADR-0084: The admin's branding is a setting over config defaults

**Status:** Accepted

**Context:** The sign-in screens and the sidebar always showed an "L" and "Lynx", and the only way to change the admin's colours was a BlatUI accent name or a stylesheet. A client signing in to their own site expects to see their logo and colours, and the person who changes them is often not a developer.

**Decision:** `config('lynx-admin.branding')` holds a name, a logo (URL or public path), a background and an accent (hex colours). Superusers override them under Settings > General; the saved values are one JSON setting, `admin.branding`, written by the `admin.branding` action (superusers only), and an empty value falls back to the config. Uploaded logos (PNG, JPEG, WebP or SVG, checked by their bytes, up to 1 MB) are sent to the action as data URLs, stored in private storage under their hash, written and deleted only after the change commits, and served by the admin's asset route with long caching; SVGs get a sandboxing Content-Security-Policy. The accent re-colours BlatUI's primary tokens through the `<html>` element's style attribute, so it wins in light and dark, with white or near-black text picked by contrast. Only six-digit hex colours reach a style attribute.

**Consequences:** The sign-in screens, the sidebar header and the browser title show the site's name and logo without a deploy. Config-only sites keep working; their logo may be any file, since it is the developer's. The accent setting and the `appearance.theme` accent name overlap: the hex accent wins where set.

## ADR-0085: The page not found is a page in the tree

**Status:** Accepted

**Context:** A URL that matched nothing aborted with 404 and Laravel rendered the app's `errors/404` view: text in code that editors could not change or translate, and that template strings could not reach either, since no template includes the error view.

**Decision:** A setting, `pages.not_found`, names a page; the `pages.set_not_found` action (superusers only) sets it, or clears it for none. Core registers a renderable for `NotFoundHttpException` that, for GET and HEAD requests outside the admin that don't expect JSON, renders that page with its template view and status 404 and `X-Robots-Tag: noindex`. The page's own URL answers with 404 too. A trashed or unpublished page, a site without one, or a render failure (reported) leave the answer to Laravel. `lynx:install` creates a hidden system page `/not-found/` with a `not-found` template (title only) and picks it, unless the setting exists, so choosing none sticks. The admin picks the page under Settings > General and marks it in the tree.

**Consequences:** Editors write the 404 text, translate it and give it template strings like any page. Existing sites get the page on their next `lynx:install`, which adds a template to their schema. Content packages carry the page like any other; the setting stays with each site.


## ADR-0086: Template strings take values, and follow Livewire components

**Status:** Accepted

**Context:** ADR-0047 required every `@string` argument to be a literal, so the scanner knows the strings without running a template. A text holding a value ("Our clocks run on Pacific time", "Kingfisher is taken from 10:00 to 11:00") could then only be split around the value, which breaks languages whose word order differs and gives editors fragments, or stay in a lang file editors can't reach. Strings in a Livewire component's view were not found either: the scanner followed `@include` and `<x-…>`, not `<livewire:…>`, so a booking form's texts never reached the page's Texts tab.

**Decision:** `@string` takes `replace:`, an array of values for `:name` placeholders, filled in after the text is chosen (code or editors' text) and before escaping, with Laravel's `:Name` and `:NAME` variants. It is the only argument that may be code; the scanner skips its expression. `StringDefinition::placeholders()` reads the placeholders from the code texts, and the Texts tab asks editors to keep them. The scanner follows `<livewire:name>` and `@livewire('name')` to the view `livewire.name`, Livewire's default, without core depending on Livewire; a component whose view lives elsewhere is not followed.

**Consequences:** Texts with values become editable in every language. An editor who drops a placeholder loses that value from the page; the hint is the only guard. Page texts in a component's view need the view to have `$page`. The scan cache's prefix changes, so every template is scanned again once.

## ADR-0087: A translatable text with nothing in the current chain reads as null

**Status:** Accepted. Amends ADR-0070.

**Context:** ADR-0070 made a translatable text read as `null` only when no language held a value, and kept it a `TranslatableValue` otherwise, so `in()` could reach other languages. A text filled in one language and read in a language that doesn't fall back to it was then an object whose value is `null` and which prints nothing. Objects are always true in PHP, so `@if ($page->field)` printed empty markup and `$page->field ?: $default` never used the default: a lawyer's job title written only in French blanked their role on the English page of a real site. The documentation already promised that an empty text reads as `null`.

**Decision:** A translatable text, on a page or in a repeater or matrix item, reads as `null` when no language in the current language's fallback chain has a value (`TranslatableValue::isEmpty()`), whatever other languages hold. A field set never to fall back only looks at the current language. Code that needs a language outside the chain reads it with `$page->getLanguageValue($language, $field)`, or reads the field inside `lynx()->language()`. `isBlank()` stays, for code that asks whether any language holds a value.

**Consequences:** `@if`, `?:` and `??` follow what the visitor sees. Code that called `in()` or `has()` on a field read in a language whose chain is empty now gets `null` and must use `getLanguageValue()`; the upgrade note says so. A page serialized for MCP and the read tools gives `null` for such a field instead of an object with a `null` value.

## ADR-0088: Field defaults are stored on new pages and items

**Status:** Accepted

**Context:** Settings fields, such as a block's image position or padding, need a value even when the editor never touches them. Without a default, every template repeats a fallback (`$block->padding?->value ?? 'medium'`), editors see an empty input while the site shows something else, and selectors such as `padding=medium` miss the pages that rely on the fallback. A default read at query time would need extra SQL for every comparison, and "no choice" could no longer be stored.

**Decision:** A field type may implement `HasDefaultValue`. `pages.save` stores its default on a new page for every template field the input leaves out, and the item normalizer does the same in a new repeater or matrix item (one without a key). A field given as empty stays empty, existing pages and items are never filled in, and importers already send every field, so imports recreate pages as exported. The admin shows a new item with its defaults already chosen. `choice` is the first type with one: the setting `default` holds an option value, or a list with `multiple`. Settings that depend on each other are checked on the merged settings through `ChecksSettings`, so `fields.update` refuses options that leave the default out. In the admin, the choices editor marks the default option, and a required select with a default offers no empty option.

**Consequences:** Stored values keep selectors, search and exports consistent with what templates read. Adding a default, or a field with one, to a template does not change existing pages; templates still handle an empty value there. Other types (toggle, integer, text) can implement `HasDefaultValue` later without new decisions.

## ADR-0089: The admin shows the Lynx mark until a site brands it

**Status:** Accepted. Amends ADR-0084.

**Context:** Without a logo, the sign-in screens and the sidebar showed the name's first letter in a square of the accent colour, and browser tabs showed no icon or the host app's. Lynx now has a mark: a faceted lynx head in three teal tones, with a 16px drawing for favicons. A fresh install should look like Lynx, but a client's admin must never carry Lynx's brand (PRODUCT.md: the client's admin, not ours).

**Decision:** `Branding::usesLynxMark()` is true while the name is the default (`Lynx`) and there is no logo, from the config or uploaded. Then the brand component draws the mark inline (`<x-lynx-admin::lynx-mark>`) where the initial was, and the document links Lynx's favicon (an ICO and an SVG committed under `resources/brand`, not built), served by the admin's asset route. Once a site sets a name or a logo, the admin goes back to ADR-0084: the logo, or the initial and the name, and no Lynx favicon, so browsers fall back to the site's own `/favicon.ico`. The mark's colours are `--lynx-mark-*` tokens in `admin.css`, not BlatUI tokens: an accent does not change artwork. In dark mode the eye takes the light tone. The SVG favicon lifts every tone a step in dark mode, since the deep tone does not read on a dark tab bar at 16px.

**Consequences:** New installs and the workbench show the Lynx mark and favicon. Sites that set only an accent or a background keep the mark. A site that names itself, even with no logo, loses both at once. The mark's sources live in `design/logo`; the admin's copies change only when the mark does.

## ADR-0090: Selectors find pages by status, language, draft and schedule

**Status:** Accepted.

**Context:** The admin's page tree needs lists such as "pages with a draft", "pages with a schedule" and "pages not in Portuguese yet", and the pages API could only answer them one page at a time (`withDrafts(ids)`, `schedules(page)`). `include=hidden|unpublished` adds pages to a result but can't select only them, and `language=pt` picks the language values are compared in without filtering anything. Anything the admin can list should be a selector, so developers, the MCP server and the AI agent can ask the same question in one query.

**Decision:** Four selector terms, compiled by `PageStateMatch`:

- `status=hidden|unpublished|locked` matches pages with any of the statuses, `status!=` with none. Naming a status lets those pages through the status filter, so `status=unpublished` needs no `include`. Like ADR-0061, the match lists the status values rather than testing bits. `unique` and `system` are bookkeeping and can't be named.
- `language!=pt` matches pages with no published name in Portuguese (`lynx_page_languages`). Several languages (`language!=pt|de`) mean none of them. The default language matches nothing, since every page is published in it. `language=pt` keeps its meaning (ADR-0010), so `!=` doesn't change the language values are compared in. The two read differently on purpose: one picks a language, the other filters by it.
- `has_draft=1` matches pages with a draft (ADR-0039), `has_draft=0` (or `!=1`) pages without one.
- `has_schedule=1` matches pages with a publish or unpublish date waiting (ADR-0036). Failed schedules don't count.

`has_draft` and `has_schedule` join the reserved field names. `status` and `language` already were. The terms can't be combined with fields (`status|title=x`).

**Consequences:** Each term is one `EXISTS` or `IN` clause, on SQLite and PostgreSQL alike. A site with a field named `has_draft` or `has_schedule` must rename it; no released site is known to have one. A visitor's text pasted into a selector can now also add `status=unpublished`, which the docs warn about with `include=`.

## ADR-0091: An action can log one activity event per page it changed

**Status:** Accepted. Amends ADR-0036's consequences.

**Context:** The activity recorder writes one event per outer action, filed under its first change. A page's history lists the events filed under it. `pages.run_schedules` applies every due entry in one run, so a minute that published three pages filed all of it under the first, and the other two pages' histories never showed it. The scheduler also runs every minute, and each run with nothing due wrote an empty event.

**Decision:** `Action::logsActivityPerPage()`, false by default. When an action returns true, the recorder splits its changes at each change to a different page and writes one event per part, each under the action's name and filed under its page. Changes to other subjects (paths, files) stay with the page before them, and a run with no changes writes nothing. `pages.run_schedules` returns true. Each entry it runs also records whether it published or unpublished and in which language, after the entry succeeds, since the nested `pages.status` or `pages.save` can say neither.

**Consequences:** Each page's history shows its scheduled changes, and the log no longer grows by an empty row a minute. Dry runs and the action's result keep the whole change set. Other actions are unchanged: a move or a trash still logs once, under the page it moved. An app's batch action (an import, a sync) can opt in the same way.

## ADR-0092: A page's draft can be scheduled to go live

**Status:** Accepted. Extends ADR-0036 and ADR-0039.

**Context:** Scheduled publishing (ADR-0036) sets when a page, or one of its languages, goes online or offline. It can't put a draft live: a home page rearranged for Monday morning still needs someone to press **Publish draft** at nine. Drafts (ADR-0039) belong to the whole page, not to a language.

**Decision:** `pages.schedule` takes `publish_draft_at`, a future time or null to clear it, beside `publish_at` and `unpublish_at`. It is stored in `lynx_schedules` as the action `publish_draft` with language id 0, whatever the request's `language`, and reads back as the language `*`. Scheduling it needs `pages.publish` and `pages.edit` on the page, and a draft to publish. `pages.run_schedules` runs `pages.publish_draft` for a due entry, so validation, hooks, a revision and activity ("Published the draft on schedule") apply as for an editor. It publishes the draft as it is at that time, not as it was when scheduled. Publishing or discarding the draft by other means clears the entry. An entry that can't run, such as one for a locked page, fails with the reason like any other (ADR-0036).

**Consequences:** Editors can prepare a change and let it go live by itself. Who scheduled it does not review the draft's later edits: the page's history shows each save. A draft publish and a page publish are independent, so an unpublished page whose draft goes live stays unpublished unless it also has a `publish_at`. No migration is needed: the action column already holds the kind of entry.

## ADR-0093: Lists keep pages not published in the visitor's language; menus leave them out

**Status:** Accepted

**Context:** A page is published in a language once it has a URL name there, and an editor can take it offline in one language. `find()`, `children()` and page references still list such a page in that language, linking to its default-language URL, because fallback happens at read time (rule 5). Menus leave it out. Nothing said so, and a site featuring pages per market (a Portuguese home page) had to filter by hand to drop a product taken offline in Portuguese.

**Decision:** The behaviour stays and is written down. Lists, searches and references keep a page that is not published in the current language, with links to the default-language URL; menus (`lynxcms/menus`), the sitemap and hreflang leave it out, as they promise URLs in that language. `PageArray::publishedIn(?Language|string $language = null)` (@api) keeps only the pages published in a language, the current one by default. Pages without a URL are kept, since they have no URL to be published at and show in every language.

**Consequences:** Existing sites list what they listed. Writing the rule down showed `Page::localUrl()` did not follow it for a page taken offline in a language (it has a name there, switched off): it linked to that language's address, which answers 404. It now links to the default-language URL, as for a page never published there, and skips that language's path lookup. A template that features pages per market writes `$section->cards->publishedIn()`. A selector term for the same filter is left for later: `language=pt` already chooses the language a selector matches in, so it would need a name of its own.

## ADR-0094: A template or block type can set a field's limits for itself

**Status:** Accepted. Extends ADR-0045.

**Context:** A field's settings were the same everywhere it was used, except a matrix's `allowed_types` per template (ADR-0045). Page fields gained `min` and `max`, and a site reuses one `cards` field in several sections and templates that want different limits: 4 cards in a row, 8 in a grid. Separate fields per use (`cards_row`, `cards_grid`) split one idea into several, for templates and selectors alike.

**Decision:** A field type lists the settings a use may set for itself with `SettingsPerUse` (@api): page fields `min`, `max` and `templates`, repeaters and matrices `min` and `max`. A template's field entry and a block type's (or repeater's) subfield entry may set them; `UseSettings` merges them over the field's own settings, and `TemplateField::config()` and `Subfield::config()` give the result. Saving validates with it, and the admin's inputs read it, so the picker lists the use's templates and stops at its `max`. Setting one a type doesn't list, or an invalid value, fails when the template or the matrix is saved. `multiple` and `input` stay per field, since they change what the value is.

**Consequences:** One field serves several uses with their own limits. A page field's `templates`, per field or per use, is still applied by the admin only, as before: saves from code do not check it. Other settings (a text's `max_length`, a number's `max`) are not per use; a type opts in by listing them.

## ADR-0095: A page field's templates hold on every save, for the pages a save adds

**Status:** Accepted. Amends ADR-0058 for page tables.

**Context:** A page field's `templates` was applied by the admin only: its picker and lists offered pages of those templates, while saves from code, the API, MCP and the agent could reference any page. Page tables checked every item on save, so narrowing a table's templates made the next save of the page fail, even one that only fixed a title, until someone removed the old items.

**Decision:** `ReferenceTemplates` runs after a field's value is normalized: it compares the page references being saved with the ones stored for that page and field, and the pages a save adds must use one of the templates its use allows (the field's, or the template's or block type's own, ADR-0094). Inside repeater and matrix items, each page subfield is checked with its own settings, against the pages that subfield holds in any stored item. Pages already held are never refused. Page fields and page tables both work this way; the page table's own check on every item is gone.

**Consequences:** An agent or an import can't add a page of the wrong kind, and the admin's picker and saves agree. Narrowing `templates` keeps what pages hold; the pages of other templates stay until an editor removes them. Moving a page from one item of a matrix to another counts as held. Each save of a reference field reads that field's stored value once.

## ADR-0096: Selectors leave out pages without a URL unless asked for

**Status:** Accepted. Extends ADR-0026's status filter.

**Context:** Pages whose template has no URL (hero slides, promos, settings pages, forms) only show inside other pages, but `find()`, `children()` and `search*=` returned them like any page. A site search found a hero slide, and a link to it led nowhere. Leaving them out everywhere would break the code that reads them on purpose: a carousel reading its slides, the forms module looking up a form, a settings lookup.

**Decision:** `UrlFilter` runs beside the status filter: a selector leaves out pages of templates without a URL unless it asks for them. It asks when it names such a template (`template=promo`, not `!=`), reads below a page without a URL (`parent=` or `has_parent=` it, so `children()` of a slide branch), names pages by `id=`, or adds `include=no_url` or `include=all`. Status rules still apply: `include=no_url` alone keeps leaving out unpublished pages. The admin's tree, search and picker already pass `include=all`; its draft and schedule filters add `include=no_url`, while "Not in Português" leaves these pages out, since they show in every language.

**Consequences:** Site searches, lists and menus no longer offer pages visitors can't open. Code that found such pages without naming their template or parent, for example with `parent=/`, must add `include=no_url`; the upgrade guide says so. Each selector reads the templates without a URL from the registry, and one indexed query checks a `parent=`/`has_parent=` reference.

## ADR-0097: The admin's wording lives in language files, in each editor's language

**Status:** Accepted. Settles the question PRODUCT.md left open; ADR-0043's "until an interface language exists" ends here for the admin's own wording.

**Context:** The forms and page password modules ship language files, but the admin and the other modules hardcode English in about 250 views. An editor on a Portuguese site works in English, and a team may not share one language. The language an editor reads and the languages a site publishes in are different things: an English site can have a Portuguese editor. Lynx never owns users (ADR-0033), and apps with their own sign-in don't load the admin's migrations.

**Decision:** Every package's human wording lives in PHP files in `lang/{code}`, under the package's namespace (`lynx-admin::auth.login.title`), so nothing collides with the app's strings, and an app overrides a line in `lang/vendor/{namespace}/{code}`. Lynx ships English, Portuguese and Spanish (added in 0.1.50); a test fails when a key or placeholder is missing from a language a package ships. The admin's language is the editor's choice, then `lynx-admin.locale`, then the app's locale, read by `AdminLocale`. `lynx-admin.locales` lists what the user menu offers. The choice is saved by `admin.set_locale` as one `Setting` row per user who made one, which needs no migration and no column on the app's users. The `UseAdminLocale` middleware runs on admin routes, the sign-in screens and, as persistent middleware, Livewire updates. It only sets Laravel's locale, never `LanguageContext`, so the language pages are read and saved in doesn't move. Action descriptions and validation errors from actions stay in English for now: agents and MCP read them too.

**Consequences:** A site with `APP_LOCALE=pt` gets a Portuguese admin as screens are translated; `LYNX_ADMIN_LOCALE=en` keeps it English. Each admin request and Livewire update reads one more setting. A deleted user's row stays, harmlessly. Field and template labels still follow the content language (ADR-0043), so an editor who chose Portuguese on an English site sees labels in the site's language. Views an app copied into `resources/views/vendor/lynx-admin` keep their English. Framework messages such as `validation.*` come from the app's own language files.

## ADR-0098: SEO keeps the site settings in the cache until they change

**Status:** Accepted. Refines ADR-0055, whose settings were read from the settings page on every render.

**Context:** Every page's SEO reads the same ten fields of the settings page: the site name, the title pattern, the defaults, the X account, the organization and the template mappings (ADR-0056). Fields load one table at a time, so that is ten queries, plus finding and loading the page, on every render the page cache does not serve: about 15 of the corporate starter's 82 queries on its home page. The values change only when an editor saves the settings page.

**Decision:** `SiteSettings` reads those values once and keeps them, as plain arrays, in the app's default cache store under one key, with one copy per host and language: they hold translated text and absolute URLs. Each copy remembers the settings page it was read from. A `ContentChanged` event clears every copy when it lists that page, or when any page may show the change (a language); field and template actions clear them after their transaction commits. The listener never looks the settings page up, since inside an action that is installing it, `SettingsPages` would remember it as missing. The page's own SEO fields are not cached: they are the page's, and the page cache covers whole pages.

**Consequences:** A render without the page cache reads one cache entry instead of the settings page; a save in the admin shows on the next request, as before. A change made outside actions (SQL, a database restore) needs `cache:clear`. The key carries a version, bumped when the shape changes, so a deploy never reads an older release's copy. On the `database` cache store the read is still one query.


## ADR-0099: A draft keeps the origin of its values when it is published

**Status:** Accepted. Completes ADR-0016 for drafts (ADR-0039).

**Context:** A value's origin (`human`, `machine`, `copied`), its provider and the hash of the text it came from decide whether a later translation may replace it and, later, whether it is out of date. `pages.save_draft` dropped the origin it was given, so a machine translation saved into a draft was recorded as human. `pages.publish_draft` saves the draft's values onto the live page through `pages.save`, which records every value it writes as human, so publishing turned all of a page's machine translations into human ones, even those the draft never touched. Translating into a draft, so an editor reviews the result before visitors see it, needs both to keep the origin.

**Decision:** `pages.save_draft` passes `origin`, `provider` and `source_hashes` on to `pages.save`, which records them on the draft. `pages.publish_draft`, after its save, replaces the live page's value metadata with the draft's. The draft started with a copy of the live page's and recorded the origin of every later save, so its rows are the true ones. Restoring a revision still records the values as human: revisions keep no origins, and a person chose to bring them back.

**Consequences:** A machine translation reviewed in a draft is still a machine translation once live, so it stays replaceable by a newer translation and protected human text stays protected. A person's edit in the draft publishes as human. Publishing writes the page's metadata rows once more.

## ADR-0100: Machine translation can write into a draft, and providers have names

**Status:** Accepted. Extends ADR-0016 and ADR-0020; builds on ADR-0099.

**Context:** Editors should read a machine translation before visitors do, but `pages.translate` wrote to the live page. A site may also use more than one provider (a translation service for prose, a local model for drafts), while the container held a single `TranslationProvider`. A dry run of `pages.translate`, and an agent's plan, called the provider for real and threw the result away, so checking cost as much as translating. A translation module needs to read loose data the way core does, through `Value`, which was internal.

**Decision:** `pages.translate` takes `draft`: the translation goes into the page's draft, created from the live page when missing, and is made from the draft's own source text, so it matches what the editor is working on. A draft names nothing (ADR-0039), so a translation into a draft never activates the page in the target language. `TranslationProviders` (`@api`) holds providers by name; `pages.translate` takes `provider`, and without it uses the container's `TranslationProvider` binding, else the first registered. `lynxcms/ai` registers `laravel-ai`. A dry run records the number of texts and characters it would send and returns before calling the provider. `Support\Value` becomes `@api`.

**Consequences:** A module can offer a provider choice and a "translate into draft" button using only public API. Plan mode and estimates are free. A page translated into a draft for a language it isn't active in needs that language turned on after publishing. The action records one change with the text and character counts and the provider, which the activity log keeps.

## ADR-0101: Modules add components to named slots in the admin

**Status:** Accepted. Sits beside `EditorLinks` and `EditorTabs`.

**Context:** A module that acts on the page being edited, such as translating it, needs a button in the page editor, and a dialog behind it. `EditorLinks` only adds links and `EditorTabs` a whole tab. When such a module writes to the page's draft, the editor's form still shows the values it loaded, and a later save would send them back over the module's work.

**Decision:** `AdminSlots` (`@api`) holds Livewire components by slot name, each with an optional closure saying which pages show it. The page editor's header renders `editor.header` beside its save buttons, for people who may edit the page on its content tabs, mounting each component with the page's id. A component that changes the draft dispatches `lynx-draft-changed`; the editor then reloads from the draft and puts back the values the person changed and has not saved. More slots (a field, the tree's toolbar, Site texts) are added when a module needs them.

**Consequences:** Paid and third-party modules add editor actions with public API only. An unsaved edit wins over the module's value for the same field and language, so nothing a person typed is lost; the module's value shows once that edit is undone or saved over.

## ADR-0102: A machine translation knows when the text it came from has changed

**Status:** Accepted. Extends ADR-0016, which stored the hash of the source text for this.

**Context:** A page translated by machine drifts from its source as editors change the original: the English summary is rewritten, the Portuguese one still says the old thing. ADR-0016 kept each translation's source hash so this could be found, but nothing read it back, and the hash alone does not say which language the source was in, since a page may be translated from any language.

**Decision:** `lynx_value_meta` gains `source_language_id` and `outdated`. `pages.translate` passes the source language to `pages.save` (`source_language`), which records it with the origin. Whenever a translatable field is written in a language, every machine translation of that field on the same page made from that language is compared with the new text: different from the hash means outdated, equal means not. Writing a translation, by machine or by hand, starts it fresh. Drafts copy the metadata whole, so a change in a draft marks the draft's translations and publishing carries the mark to the live page. `lynx()->pages->translationStates($page, $language)` (`@api`) returns each field's origin, provider and outdated flag. Item fields keep no origin (ADR-0044) and are not tracked. The migration records the default language as the source of existing machine translations, the only one `pages.translate` used without being told.

**Consequences:** The admin and modules can show "source changed" next to a field and offer to translate it again. Each save of a translatable field runs two small updates on the metadata table. Selectors cannot yet find pages with outdated translations; that comes with a selector term when a screen needs it.

## ADR-0103: Selectors find outdated translations, and modules add tree filters

**Status:** Accepted. Extends ADR-0090 and ADR-0102.

**Context:** ADR-0102 marks a machine translation outdated when its source changes, but an editor only sees that on a page they open. To find the pages that need another look, the page tree's Details view, whose filters are selectors (ADR-0090), needs a term for it, and the module that translates needs to add the filter without core knowing about it.

**Decision:** `has_outdated=<code>` (and `|` for several, `!=` for none) matches pages with an outdated machine translation into a language, on the page or in its draft, since the editor shows the draft. The name is reserved for fields. `TreeFilterRegistry` (`@api`) holds closures returning filters by key, each a label and a selector term; the tree lists them after its own, over the same pages, and a module filter cannot replace a built-in key.

**Consequences:** "Outdated in Português" is one module line. Each filter costs a count query when the Details view opens, as the built-in ones do. A field already named `has_outdated` would stop working in selectors, as with `has_draft` in 0.1.24; the release notes say so.

## ADR-0104: Extensions wrap every translation provider

**Status:** Accepted. Extends ADR-0100.

**Context:** A translation module needs to see every call to a provider, whichever one the editor picked and whether the call comes from its own screens, the free `pages.translate`, an agent or MCP: to count characters against a monthly budget, and to hand every provider the site's glossary. Wrapping only the providers the module registers would miss `laravel-ai` and any other package's.

**Decision:** `TranslationProviders::extend()` (`@api`) adds a closure that receives each provider `get()` hands out and returns the one to use, usually a decorator around it. Extensions apply in the order added; listing names does not apply them. The `TranslationProvider` contract documents the option keys providers should honour when they can: `provider_code`, `glossary` (source term to target term), `formality` (`more` or `less`) and `context`. The `laravel-ai` provider writes the glossary, the tone and the context into its prompt.

**Consequences:** A budget or a glossary applies to every provider without core knowing about either. A decorator must keep the inner provider's `name()`, since values record it as their provider.

## ADR-0105: Machine translation reaches texts inside values

**Status:** Accepted. Extends ADR-0044 and ADR-0047.

**Context:** `pages.translate` filled a page's text fields and the text subfields of its items, but a page holds other texts per language: a picture's description, a form question's option labels, and the page's own template texts. Editors translating a page by machine found those still in the source language. None of them records who wrote it, so a translation must never replace one.

**Decision:** Field types whose shared value holds texts per language implement `HasTranslatableTexts` (`@api`): the texts missing in a language, and the value with translations put in. The forms module's options type does. `ItemTranslations` also translates an item field's translatable properties (`ItemProperty::isTranslatable`, a picture's description) and the texts of subfields whose type has them. `PageStrings::untranslated()` (`@api`) lists the page's own template texts, or the site's, with no text in a language from an edit or the code; `pages.translate` sends the page's own as `@strings.<key>` and saves them through `pages.save`, into the draft when asked. Only missing texts are filled; a template text whose translation drops a placeholder is left out. Site texts are not part of a page and are not drafted, so a module translates them from the Site texts screen (`AdminSlots::SITE_TEXTS_HEADER`) after a person reviews them.

**Consequences:** One translation covers what a visitor reads on the page. "Also replace earlier machine translations" does not reach these texts; a person edits them or clears them first.

## ADR-0106: Modules outside the monorepo ship their own admin stylesheet

**Status:** Accepted. Builds on ADR-0034 (BlatUI) and ADR-0071 (built assets).

**Context:** The admin's stylesheet is built from Lynx's own views (`@source`), so a module in its own repository, such as a paid one, finds that classes only it uses do nothing: a dialog meant to be wider stays narrow, a grid stays a column. Rebuilding the admin's stylesheet on each site would need Node on the server; growing it in advance is a guess that never ends.

**Decision:** The admin ships `resources/css/module.css` (`@api`): Tailwind's utilities with its theme and BlatUI's and Lynx's token mappings imported as references, so a module's build gets the admin's class names and defines no token. BlatUI's mapping moves to `blatui-theme.css` and Lynx's additions to `lynx-theme.css`, which both stylesheets import; the admin's built CSS is unchanged. A module builds its own sheet from it and registers it with `AdminAssets` (`@api`); the admin serves it at `lynx/assets/modules/{name}.css` with the file's time as its version, and links it after `admin.css` and before `lynx-admin.stylesheets`.

**Consequences:** Module screens follow the appearance presets, the branding and dark mode, since their classes read the same variables, as long as the module defines no tokens and uses the admin's form components; Extending the admin says so. Each module adds one cached request and a few KB, some of it rules the admin already has.

**Amended (0.1.41):** those repeated rules were not harmless. Loaded after `admin.css` in the same `utilities` layer, a module's `.hidden` beat the admin's `md:flex`, and the admin's sidebar disappeared. `module.css` now imports the utilities into a sub-layer, `utilities.module`: rules placed directly in a layer beat its sub-layers' rules, so wherever the admin has a class its own rules win, and a module's classes apply only where nothing else does. `module.css` and the two theme files become public API: renaming a token or variant breaks modules and needs a release note. Modules must build with the admin's Tailwind major version.

## ADR-0107: Rich text fields turn on extra editor tools per field

**Status:** Accepted.

**Context:** Some sites need superscript and subscript (units, chemistry, footnotes), underline, highlight, code, a horizontal line or centred text. Adding them to every rich text editor lengthens every toolbar, and editors use what they see: a news site would start centring paragraphs.

**Decision:** `textarea` gains the setting `tools`, a list from `TextareaType::TOOLS` (`superscript`, `subscript`, `underline`, `highlight`, `code`, `code_block`, `horizontal_rule`, `text_align`), empty by default. The field form shows it as checkboxes while the field is rich text; `SchemaForm` now reads a list's choices from its items' enum, so any type's list of choices gets checkboxes. The setting only adds toolbar buttons: the editor always loads every extension, so markup a hidden tool made (by an agent, an import or before the tool was turned off) survives a visual edit. `HtmlCleaner` keeps `sup`, `sub` and `mark` besides the tags it kept already, and a `style` on `p`, `h2`, `h3` and `h4` through `TextAlignSanitizer`, which keeps only `text-align` with `left`, `center`, `right` or `justify`, written one way (`text-align: center`).

**Consequences:** Turning a tool off never changes stored text, and the editor still accepts its keyboard shortcuts and Markdown-style input rules, since the extensions stay loaded. Saved text can now carry an inline style, limited to alignment; a site's CSS styles `mark`, `sup` and `sub`. Four Tiptap packages join the admin's build, pinned to its Tiptap version.

## ADR-0108: A section is copied between templates, not shared

**Status:** Accepted.

**Context:** Sites repeat groups of fields on several templates: an address, a call to action, contact details. A shared, live "fieldset" that templates include was designed: its own entity and screen, fields it owns, sections, per-template text overrides, and every change spread to the templates using it. It needs two tables, expansion and propagation through `templates.update`, a nested block in the editor layout and restrictions on owned fields in every field picker. Fields are already shared between templates, and code or schema files reuse a group of fields as a plain list, so the live link mainly helps sites that edit their schema in the admin.

**Decision:** `templates.copy-section` copies a section, with its fields' settings on that template and its tab's definition, into other templates through `templates.update`, in one transaction. A template that already has one of the fields, or a section with the same key, is refused. With `rename_conflicts`, a clashing field is cloned instead as `prefix + name` (type, label, translatable and settings), once per run, and conditions inside the section that read it are rewritten (`FieldCondition::renamed()`); a clone whose name is taken is refused. The template board's section menu opens a dialog that lists the other templates, except those a module owns, with each one's reason from a dry run and the renames it would get.

**Consequences:** No new entity, and nothing changes for templates after a copy: each owns its section, so a later change is made per template. Renamed fields are new fields, so values are never copied or shared. Live fieldsets stay possible later; this ADR records why they were parked.

## ADR-0109: lynx:doctor runs read-only checks that modules extend

**Status:** Accepted.

**Context:** "Why doesn't my page show its fields?" usually has a known cause: no view for the template, a field never placed, values left from a removed field, a scheduler that never runs, a missing `storage:link`. Each is easy to find once you know where to look, and each module knows its own.

**Decision:** `lynx:doctor` runs the checks in `DoctorChecks` (`@api`): Lynx's own (template views, unused fields, orphaned values, scheduler) and those modules add at boot with `add()`. A check (`Check`, `@api`) has a key, a label and `run()`, which returns `Finding`s (`@api`: a `Severity`, a message and an optional fix) and never writes. The command prints OK per passing check, or each finding with its fix; `--json` prints the report. It exits with 1 only on an error, so deploy scripts can stop on one. Only signals Lynx can read with certainty become checks: whether a queue worker runs or the search index is current cannot be told from the database, and language fallback loops are already refused by `languages.update`.

**Consequences:** Checks only read, so the command is safe in production and in CI. Their wording is English, like other console output. A module's check runs wherever the module is installed; a slow one slows the whole report, so checks should use counts and existence queries, not page loads.


## ADR-0110: Selectors take OR groups, one level deep

**Status:** Accepted.

**Context:** Selectors AND every clause. OR exists between values (`template=a|b`) and between fields (`title|summary%=x`), but not between different conditions, so "tagged ai, or priced from 50" needs two queries merged in PHP, which breaks sorting, paging and counts. The planned Page Finder screen lets people group conditions and combine the groups with all or any, so the selector has to say it. ProcessWire, where Lynx's selectors come from, writes this as OR groups.

**Decision:** A parenthesised part is an OR alternative: `(price<20), (price>70)` matches pages that match either group, and inside a group commas mean AND. Groups that share a name form a set of their own (`cost=(…), cost=(…)`), and every set must match, so several ORs can be combined. A parenthesis opens a group only at the start of a part, so values like `Risk (basics)` keep theirs, and a value that starts with one is quoted. Groups are one level deep. Inside a group, terms that act on the whole selector (`sort`, `limit`, `start`, `include`, `join`, `language=`, `search`) are refused; page states (`status`, `has_draft`, `language!=`…) work, and a status named inside a group lets that status through for the whole selector, as it does outside. `SelectorParser::parseSelector()` returns a `ParsedSelector` (clauses and `OrGroupSet`s, `@api`); `parse()` keeps returning clauses and refuses a selector with groups rather than drop them. `SelectorCompiler` turns each set into a nested `where` of `orWhere` alternatives, each compiled by the same clause code as the top level, so every field kind works inside groups.

**Consequences:** Code, the admin search, MCP and the agent can express any one-level boolean search in one query, with sorting, paging and `total()`. Deeper nesting is not possible and is refused rather than misread. A value starting with `(` after `name=` reads as a group, so it must be quoted. OR groups over translatable fields join the same value tables as the top level, so they cost about what the same clauses cost outside a group.

## ADR-0111: Saved finder searches are rows owned by one user, shared with a permission

**Status:** Accepted.

**Context:** The Page Finder (`lynxcms/finder`) keeps its search in the URL, which is enough to bookmark one, but editors want a list of the searches they run every week ("courses without a summary"), and a team wants a few shared ones. A search could live in the user's settings, in site settings, or in a table of its own. Users in Lynx are the host app's, with ids that may be numbers or uuids, and Lynx has no user settings store.

**Decision:** Saved searches are rows in `lynx_finder_searches`: a name, the search as `FinderQuery::toArray()` writes it, `owner` (the actor as a string, `user:5`, as forms store `read_by`) and `is_shared`. The module writes them only through two actions, `finder.save_search` and `finder.delete_search`, so MCP, the agent and the activity log see them. Saving needs `pages.view`; sharing needs the new permission `finder.share`; changing or deleting a saved search needs to be its owner or a superuser. The screen lists the user's own searches and the shared ones by name. A saved search stores conditions, not results, so it runs again with the viewer's own scope: sharing a search never shows a page someone may not view.

**Consequences:** Searches survive across devices and can be shared without touching site settings. A search that names a field later deleted keeps the condition, and the finder leaves it out until the field returns, as it does for any incomplete condition. Searches of a user who leaves stay until a superuser deletes them.

## ADR-0112: The admin keeps configuring Fortify, guarded by tests in the app's provider order

**Status:** Accepted (extends ADR-0041)

**Context:** In `lynx` mode (ADR-0041, ADR-0074) the admin configures Laravel Fortify while its service provider registers: it writes `fortify.prefix`, `fortify.middleware`, `fortify.home`, `fortify.views`, `fortify.lowercase_usernames`, `fortify.limiters.*`, `fortify.redirects.logout`, and `fortify.features`, and sets Fortify's views and password reset action through its static methods. Fortify reads most of these when it boots, after every provider has registered. It reads some earlier: while it registers, it copies settings into laravel/passkeys (`passkeys.redirect` from `fortify.home`). Apps discover packages in alphabetical order, so Fortify registers before `lynxcms/admin` and copies its defaults; the admin therefore writes `passkeys.redirect` itself. The test suite registered the admin first and never ran the app's order. The admin's migration also copies Fortify's two-factor columns and the passkeys table, for apps that have not published those migrations. None of this is promised by Fortify's public API, so a release that reads its config at another time, copies more of it, or changes its tables would change Lynx's sign-in without an error. An upgrade review ranks this the admin's largest risk. Two ways out were considered. `lynx:install` could publish `config/fortify.php` and a Fortify service provider set up for the admin, so the app owns the setup. Every site would then hold its own copy: a Lynx release that adds a rate limit, a feature, or a passkeys option would need each site to edit its published files by hand, untested. `auto` mode also takes an existing `config/fortify.php` to mean the app signs users in (ADR-0074), so publishing one would need a new marker. The admin could instead register Fortify's routes and controllers itself, which ties Lynx to Fortify's controllers and pipelines, a larger and less stable surface than its config.

**Decision:** The admin keeps configuring Fortify, under these rules:

- It writes only the settings that Fortify's published `config/fortify.php` documents, and uses Fortify's static methods for views and actions.
- Settings Fortify copies into another package while it registers are written by the admin as well, for each copy Lynx depends on. Today that is `passkeys.redirect` only. `passkeys.throttle` was dropped: Fortify registers the passkey routes itself, limited by `fortify.limiters.passkeys`, and never reads it.
- `tests/ProviderOrder` runs with Fortify and passkeys registered before the admin, as in an app, and checks what each setting produces: the sign-in routes under the admin prefix, the admin language and Lynx's rate limits on them, the password check before a passkey is added, the redirect after a passkey sign-in, and the limit on wrong passwords. A new setting comes with a test there.
- The admin's migration keeps adding the two-factor columns and the passkeys table only when the app's own Fortify or passkeys migrations have not run. `SignInSchemaTest` runs those upstream migrations and compares the tables with the admin's, column by column, with indexes and foreign keys.

**Consequences:** A Lynx release can change the sign-in screens for every site at once, and sites keep no Fortify files of their own in `lynx` mode. A Fortify or passkeys release that reads its settings at another time or changes its tables fails CI instead of reaching sites. Lynx still depends on how Fortify reads its config, so a Fortify major needs these tests green before the constraint widens. Apps with their own `config/fortify.php` are untouched (ADR-0074).

## ADR-0113: Signed in users who may view a page see it on the site before it is published

**Status:** Accepted (extends ADR-0039)

**Context:** An unpublished page, and every page below one, answered 404 to everyone. New pages start unpublished, so the editor's **View page** link led a superuser to "not found" until the page went live. Draft previews (ADR-0039) already show signed in editors what visitors cannot see yet, but only for a draft's values on a published page.

**Decision:** The site's page route shows an unpublished page, or a page below one, to a signed in user the `lynx.pages.view` gate allows for that page (superusers always). The response carries a bar at the top saying the page is not published, `Cache-Control: no-store, private` and `X-Robots-Tag: noindex`, and does not set the request's page attribute, so the page cache never stores it (ADR-0064). Pages in the trash still answer 404 to everyone, so a trashed page's old URL never redirects into the trash (ADR-0035). Visitors, signed in users without `pages.view` on the page, static exports and cache warming (which run as a visitor, ADR-0078) get 404 as before. Unique name shortcuts and other-language paths still lead only to published pages.

**Consequences:** **View page** works for a new page, and editors check a page on the site before publishing it. Lists, menus and selectors still leave unpublished pages out, so links to the page from elsewhere on the site appear only once it is published. A signed in editor no longer sees the 404 a visitor gets; the bar says so.

## ADR-0114: Only superusers whose account still exists count as superusers

**Status:** Accepted (amends ADR-0033)

**Context:** Lynx keeps user ids as strings without a foreign key to the app's users (ADR-0033), so deleting an account leaves its role assignments behind. The last superuser check counted assignments: with a deleted superuser's row still there, it let the only real superuser lose the role, and nobody could manage roles from the admin. `PermissionHolders::hasSuperuser()` answered yes for the same row, so the admin's first-run hint and the sign-up warning (ADR-0073) stayed silent. Removing roles when an account is deleted was considered and rejected: the delete event also fires on soft deletes, a failed removal would stop the app's own delete, and bulk deletes fire no event, so Lynx would clean up some deletes and not others.

**Decision:** `Permissions\Superusers` answers who is a superuser. A superuser assignment counts when the default guard's user provider still finds the user (`retrieveById()`). An id that can't be an integer key of an Eloquent provider with integer keys counts as gone without a query, since PostgreSQL would fail the query and the action's transaction with it. Without a user provider every assignment counts. A role change is refused when it would remove the last superuser assignment (as before, which keeps open mode from coming back) or the last assignment of a superuser whose account exists. `hasSuperuser()` counts only existing accounts. Assignments of deleted accounts stay until someone removes them; the Users page in the docs shows a listener for apps whose ids can come back.

**Consequences:** A site always keeps a superuser who can sign in, unless the app deletes that account itself, in which case the hints say no one is a superuser and `php artisan lynx:superuser` makes one. Role changes and the first-run checks look up each superuser through the user provider, one query per superuser for Eloquent. Tests that make a superuser out of an id with no account now create the account (`userAccount()`).

## ADR-0115: Core knows which schema belongs to Lynx and its modules

**Status:** Accepted (extends ADR-0019)

**Context:** Modules install templates and fields of their own: the menus module adds `menus`, `menu`, `menu-item` and its `menu_*` fields, SEO, the sitemap and the page cache add settings templates, forms adds `forms` and `form`, and Lynx adds `trash` (ADR-0035). The admin knew which module owned what through `LynxCms\Admin\Editor\SchemaOwners`, filled by each module's `AdminIntegration`, and used it to show that schema read only and to hide it from lists. Two things followed. `lynx:templates` and `lynx:fields`, which live in core, could not tell a site's templates from a module's, so a site's own schema was mixed with what it merely installed. And a site without the admin package had no record of ownership at all, although the schema is data that exists with or without the admin. ADR-0019 puts data hooks in core and UI hooks in the admin; ownership is data.

**Decision:** `LynxCms\Core\Schema\SchemaOwners` (`@api`) is the registry of who owns a template or field. Core registers `trash` for Lynx. Each module registers its templates and fields from its own service provider, before and regardless of its admin integration. The first module to claim a name keeps it. The `not-found` template is the site's: Lynx creates it at install, but each site writes its view and content. `LynxCms\Admin\Editor\SchemaOwners` keeps its public methods and reads ownership from core; its `register()` forwards to core and is deprecated. `lynx:templates` and `lynx:fields` list the site's schema first and the owned schema in a second table with an Owner column; with `--json` every row carries `owner`, `null` for the site's.

**Consequences:** The CLI, MCP tools and any future code in core can tell system schema apart without the admin. Modules written against the admin's `register()` keep working, and only show their ownership once the admin is installed until they move the call to their provider. Ownership is still a runtime registry, not a column, so a template a module no longer registers (the module was removed) reads as the site's.

## ADR-0116: Drafts are a short way to write schema, compiled into the schema files

**Status:** Accepted (builds on ADR-0014 and ADR-0115)

**Context:** Laravel Blueprint lets developers describe models in a short `draft.yaml` and generates the code. Lynx's schema is data, not code, and ADR-0014 already keeps it in YAML: one file per field and template, keyed by uuid and applied through the actions. Those files are written for machines: they need uuids and spell out every setting, so nobody writes a new site's schema in them by hand. The admin and the PHP API are the other ways in, and both take one call or one screen per field and template. Three ways to use a short format were considered. A generator that writes the schema files, with the draft as a starting point, keeps one source of truth. A second, short format that replaces the schema files is nicer to read, but needs a round trip between two formats and somewhere to keep the uuids. Building straight into the database leaves nothing in Git unless the developer exports afterwards.

**Decision:** A draft is a generator input. `php artisan lynx:draft` reads `lynx/draft.yaml` (fields and templates), compiles it into the items the schema files hold, and applies them with `SchemaSync::applyItems()`: those items only, through the actions, in one transaction, without deletes.

- **Short form.** A field is `type[:argument]` followed by tokens. The argument fills one setting per type (`decimal:2` is two places). `translatable`, a bare setting name for true, `label:"Text"` and `key:value` are the tokens. The long form is the field as a map. In templates, `name*` is required there, `{name: definition}` defines a field in place, and family rules have short keys (`url`, `children`, `parents`, `allowed`).
- **Everything is checked first.** Unknown types, settings and template keys, with the closest name suggested. Fields that neither the draft nor the site defines. A repeater listing a repeater (apply creates item fields one level deep). A change of a field's type or translatable. Schema owned by Lynx or a module (ADR-0115).
- **Adds and changes, never removes.** An item the site has keeps its uuid, keeps its label when the draft gives none, and keeps settings the draft doesn't name, so building a draft twice changes nothing.
- **A template's field list is the draft's.** It says which fields the template has and in what order, sections included, so it replaces the site's list. A draft that leaves fields out of a template the site has is refused, naming them, unless `--prune-fields` (`prune_fields` for the action) is given; then the plan lists each one as removed. The fields and their values stay. Merging the lists instead would make removing or reordering a field impossible from a draft. Each field keeps the settings it has on that template (label, description, tab, width, required) when the draft only names it, as labels and field settings are kept; what the draft sets wins, `null` clears a setting, and the draft's layout decides the field's section. Without this, naming a field dropped those settings silently, since the check above compares names only.
- **Files only where the site keeps them.** After a build, a starting view is written for each template with a URL that the draft creates or changes and that has none (`lynx:install`'s `home` has none), through the same code as `lynx:make:view`, keeping a view that exists; `--force-views` replaces only new templates' views, and templates the draft leaves unchanged get nothing, so rebuilding writes nothing. Each of those templates also gets a view for each block type of its matrix fields that has none. Block views belong to the field, which other templates may share, so a draft never replaces one, even with `--force-views`. The draft's files are written into `lynx/schema` and the PHPStan and IDE types refreshed, only when the site already has that folder and those files. Partial files in a new schema folder would make a later `lynx:schema:apply --force` delete everything else.
- **No manifest, no erase.** Blueprint keeps one so it can delete what it generated. Here `git diff lynx/schema` shows what a build did, and Git undoes it.
- **Beyond building:** `--dry-run` lists the plan (create, update, remove, write, keep, refresh), which a terminal build shows before asking. `--from-site` writes the site's own schema as a draft, in the short form where reading it back gives the same field, and building it changes nothing. `--json-schema` writes a JSON Schema built from the field type registry, for editors.
- **Agents.** The `schema.draft` action (`schema.manage`) builds a draft for MCP clients and the agent. It changes the database only, never files. Like every schema action, the runner refuses it where `lynx.schema_editing` is off (ADR-0117).

**Consequences:** A new site's schema can be written in a page and reviewed before it exists. `lynx/schema` stays the record, and `lynx:schema:apply` is unchanged. A draft can't delete, rename or retype a field; those stay with the admin, the actions and `lynx:schema:apply --force`. Pages are created only when the draft lists them (amended below). A draft can't express everything the long form can in its short form, so `--from-site` falls back to the long form, which is less pleasant to read for repeaters and matrices.

**Amended (0.1.44): pages.** A draft's `pages` section lists pages by URL path, created after the schema through `pages.save`, unpublished, in one transaction, with their titles and the values given; other fields stay empty. Required fields can't be left empty on a save, unpublished or not, so the draft must give their values (files excepted, as on any new unpublished page) rather than Lynx inventing them. The template defaults to the one named like the last segment. A path the site has is kept as it is, so a draft stays safe to build again. Every refusal a save would make is checked before anything changes, for templates the draft has yet to build too: family rules through `FamilyRules::refusalBetween()`, which the save itself uses, singletons, missing parents and unknown fields. Pages are content, not schema: they stay out of `lynx/schema` and `--from-site`, and the `schema.draft` action refuses a draft that has them.

**Amended (0.1.45): translatable by default.** A field whose type can be translated (text, textarea and URL in core) is translatable unless the draft says `translatable:false` (`translatable: false` in the long form). A separate word such as `shared` was considered and left out: it is one more term to learn, and the long form already names the flag `translatable`. The first version made every field shared unless marked `translatable`, the reverse of the admin, which on a site with one language makes such fields translatable so a language added later can be translated. Whether a field is translatable can't change after it is created, since a translatable field's table is keyed by language, so the draft's default decides for good. A field the site has keeps its own choice unless the draft states one, so drafts built before still build without changes. `--from-site` writes `translatable:false` where a field differs from the default, and nothing where it doesn't.

**Amended (0.1.46): translated choice options.** A choice field's options can be a map of default language labels to their translations (`{Beginner: {pt: Iniciante}}`), each value the label slugged as in the short form; `--from-site` writes it whenever every option fits. A list of labels in several languages, matched by position (`pt:Iniciante,Avançado`), was considered and left out: reordering one list and not the other would swap labels with nothing to catch it. A draft that gives an option the site has a label in one language changes only the default language's, keeping translations made in the admin, as field labels and settings are kept.

**Amended (0.1.57): languages.** A draft's `languages` section adds and changes languages before its fields and templates, so labels may use a language the same draft adds. Each entry is a code with a name, or a map of `name`, `default`, `label`, `locale`, `prefix`, `fallback` and `active`. `DraftLanguages` compiles it into the items `LanguageSchemaSync` applies, and `DraftRunner` applies them, then compiles and builds the rest, in one transaction. The rest is compiled after the languages exist, because compiling reads the site's labels and default language code. Remapping old codes to new ones inside the compiler was considered and left out: labels sit in field labels, settings at any depth and template field settings, and `UpdateLanguage` already moves them on a rename (ADR-0043). The entry marked `default: true` is the site's default language whatever its code is now, so a new site's `default` can become `en`, keeping its empty URL prefix. Making another language the default is refused, as `lynx:schema:apply` refuses it. A language the draft leaves out stays, as fields and templates do. When the languages change and the site keeps `lynx/schema`, every file is rewritten, since a renamed code changes labels everywhere and the language's own file is named by its code. `--from-site` writes the languages of a site with several, and leaves out settings that match what a new language gets. The `schema.draft` action takes languages too, under `schema.manage`, which already covers them.

## ADR-0117: Schema editing is a core setting, enforced by the action runner

**Status:** Accepted (changes the schema_editing flag of the schema admin plan; builds on ADR-0014, ADR-0033 and ADR-0115)

**Context:** `lynx-admin.schema_editing` off means "this site's schema comes from files": teams set it on production so the schema only changes through `lynx:schema:apply`, and nobody's live edit is overwritten by the next deploy. It lived in the admin's config and only made the admin's screens read only. The actions behind them (`fields.create`, `templates.update` and the rest) still ran for anyone with `schema.manage`, so an MCP client or the agent changed the schema on a site that said it couldn't, and the next apply with `--force` either removed the change or failed. A site without the admin couldn't lock its schema at all. The `schema.manage` permission can't express it: it is given per role, while this is a property of the environment, and it also guards actions that change no schema (rebuilding the search index, purging orphaned values, cleaning HTML values).

**Decision:** The setting moves to core as `lynx.schema_editing` (env `LYNX_SCHEMA_EDITING`, falling back to `LYNX_ADMIN_SCHEMA_EDITING`). Actions that change templates, fields or languages say so with `Action::changesSchema()`. The action runner checks it at every depth, after the permission check: where schema editing is off, it refuses people, MCP clients and the agent with an authorization error that points to `lynx:schema:apply`. Checking nested actions too means a module's own action can't add a field to a template on a locked site. The command line and the system are let through, so applying the schema files and installing need no special path: they are how the schema changes there. `LynxCms\Core\Schema\SchemaEditing` holds the rule and whether a template or field can be edited (not owned by a module, editing on); the admin's `SchemaOwners` reads it, and its screens decide nothing of their own.

**Consequences:** One setting covers the admin, MCP, the agent and any other caller of the actions. Sites that published the admin's config and set the flag there must move it to `config/lynx.php`. On a locked site, enabling a module that adds fields to templates from the admin is refused; that change goes into the schema files like any other. Artisan commands, including `lynx:draft` and Tinker, still change the schema on a locked site, since a developer with a shell there could anyway. New schema actions, in core or in modules, must override `changesSchema()`; a test lists core's.

## ADR-0118: A shared field can be made translatable, and never the other way

**Status:** Accepted (builds on ADR-0004, ADR-0044, ADR-0116 and ADR-0117)

**Context:** Whether a field is translatable was fixed at creation, because the two kinds are stored differently: a translatable field's table is keyed by page and language, a shared field's by page alone, and a repeater or matrix keeps a translatable subfield's values in one row per language rather than in the item's row. A field made shared by mistake, often on a site with one language (by a draft before 0.1.45, from code, or by choosing "the same in every language"), could never be translated once a second language came. The only way out was a new field, copying values by hand and swapping it into every template. Converting both ways was considered. Shared to translatable loses nothing: each value becomes the default language's, and fallback shows it in every other language as before. Translatable to shared must pick one language and delete the rest.

**Decision:** One action, `fields.make_translatable` (`schema.manage`, refused where schema editing is off, ADR-0117), turns a shared text, textarea or URL field into a translatable one; there is no action for the other way. It marks the field translatable, rebuilds its table with `language_id` (dropping the old table's indexes first, since index names are global on PostgreSQL and SQLite, then copying every row as the default language's with `INSERT … SELECT`), and moves the field's values in every repeater and matrix that shows it from the item rows into the default language's rows. Drafts are page rows (ADR-0039), so they convert with the table. Pages with values are indexed for search again. Value origins need nothing: shared fields record none, and a value without a record is a human one. Stored snapshots keep their old shape, so `Revisions::saveInput()`, which revisions, drafts being published, content imports and page copies go through, reads a missing default-language value from the shared slot (`*`). `lynx:schema:apply` and drafts run the action when a file or draft says translatable for a shared field, and still refuse the other way. The admin offers "Make translatable" on a shared field's screen whenever its type can be translated, with one language too.

**Consequences:** Choosing shared by mistake costs one click, and visitors see no change when it happens. The other mistake still has no fix but a new field, which is why new fields default to translatable where the type allows. A field listed in `lynx.sort_keys` needs `lynx:sort-keys` afterwards, since its keys were never built while it was shared; until then sorting reads the values directly. The rebuild locks the field's table for as long as the copy takes, which large sites should schedule.

## ADR-0119: Search finds singular and plural forms through a plural reducer per language

**Status:** Accepted (amends ADR-0054)

**Context:** The search index matches word beginnings (ADR-0054). `livro` finds "livros", but `livros` does not find "livro", and plurals that change the end of the word (`animais`, `estações`, `pães`, `homens`) are never found from the singular. Full stemming (Snowball) also merges verb forms and derivations, which makes results harder to predict, and PostgreSQL's language configurations would make PostgreSQL find pages SQLite does not. A PHP extension or a Composer dependency would break shared hosting.

**Decision:** Lynx reduces plurals only, with a hand-written PHP reducer per language. `WordReducer` (`@api`) turns a lowercased word with its accents into its singular, or returns it unchanged. `WordReducers` (`@api`) holds them by locale: a language finds its reducer by `Language::appLocale()`, the full locale first (`pt-BR`), then the bare language (`pt`); a site registers or replaces one in its service provider. The built-in reducers and their `EndingRules` base are `@api` and open to extension, so a regional locale (fr-CA) can add exceptions to `parent::exceptions()` or endings to `parent::endings()`. Core ships Portuguese, English, French, Spanish, Italian, German and Dutch, one reducer per language: regional variants (pt-BR, en-US, en-GB) reach it through the bare language, since their plurals are the same, and a site registers a full locale only where a variant differs. Italian and German plurals change the end of the word in ways no rule can undo (libro and libri, Blume and Blumen), so their reducers give singular and plural the stem they share; since index and query are reduced alike, either matches, at the cost of words that differ only in those endings meeting (casa and caso). Words under 4 letters are never reduced. Text is lowercased, reduced, then folded, so the rules can read accents and `estação` still meets `estacao`. An index row holds each folded word followed by its folded reduced form when it differs, without duplicates. A query word matches its own beginning or its reduced form exactly, never as a prefix, so `livro` does not reach "livraria" through the reduction: `("livros"* OR "livro")` on SQLite, `(livros:* | livro)` on PostgreSQL, two `LIKE` conditions elsewhere. Reduced forms still hold only letters and digits, so engines build their match from safe terms as before. `lynx.search.reduce_plurals` (on by default) switches it off.

**Consequences:** `livros` finds "livro", `animal` finds "animais", on every database alike. A rule that misfires usually yields a non-word, which matches nothing; misfires that land on real words go in the reducer's exceptions. Verb forms and derivations are still not matched. Existing indexes need `lynx:search-index` once; until then queries find everything they found before, since the reduced form only adds matches. Rows grow by the reduced forms of the plurals they hold. Measured with `lynx:bench` on SQLite, 10,000 demo pages, median of 9 runs: a search took 1.9 ms before and 2.1 ms after; a plural search, which found nothing before, takes 1.5 ms; rebuilding the index took 4.4 s either way. The demo titles hold no plurals, so the index size barely moved there; real text adds one term per plural word. On PostgreSQL 18 with the same pages, a search took 5.5 to 10 ms with or without plurals (the spread between repeated runs was larger than any difference), a plural search 6 to 8 ms, and a rebuild about 9 s; each rebuild leaves dead rows for autovacuum, which is what grew the table between runs, not the reduced forms.

## ADR-0120: The admin shows what the search index holds and rebuilds it

**Status:** Accepted (builds on ADR-0054 and ADR-0119)

**Context:** Whether a page is found depends on which fields its template marks searchable, whether the field's type gives text at all, and whether the index was rebuilt after settings changed. None of this was visible: editors could only guess why a page was not found, and rebuilding needed the command line or an agent. Rebuilding asked for `schema.manage`, though it changes no schema.

**Decision:** A `search.manage` permission covers looking into the index and `search.rebuild`, which no longer asks for `schema.manage`. `SearchIndexReport` (`@internal`) says, per language, how many pages have a row out of how many pages there are and how many have their own title in it (every page has a row in every language, the untranslated ones holding their fallback text, so a full count says nothing about translations), which fields each template indexes (the title and the searchable fields whose type gives text), with the templates of Lynx and its modules listed apart under their owner (ADR-0115), and what a page's rows hold. The admin's Settings get a Search index screen: an Overview tab with that report, and a Tools tab with `search.rebuild` (every page, a site template, or a system template chosen in a field of its own) and Test indexed words, a page lookup. The `search_inspect` read tool gives agents the same report. In the page editor, superusers see "searchable" next to a field's type when the field goes into the index.

**Consequences:** Editors with `search.manage` can check and fix the index without a developer. A role that rebuilt the index through `schema.manage` needs `search.manage`; superusers have both. A rebuild from the admin runs in the request, so very large sites should keep using `lynx:search-index` or rebuild one template at a time.

## ADR-0121: != on a translatable field is the exact opposite of =

**Status:** Accepted (amends ADR-0005; issue #194)

**Context:** On a shared field, `!=` finds every page `=` does not, pages without a value and pages whose template lacks the field included. On a translatable field, `!=` compared the effective value, the `COALESCE` along the language chain, so a page with no value in any language gave `NULL`, `NULL <> 'x'` was not true, and the page was left out. `summary!="Draft"` missed pages without a summary, and the same selector meant different things depending on whether a field was translatable. Comparing the `COALESCE` also kept the clause from using the field table's index.

**Decision:** `!=` with non-empty values on a translatable field compiles as "not among the pages `=` finds": `TranslatableMatch` builds the same per-language lookup it uses for `=` (each served by the `(language_id, data, page_id)` index) and the page query takes `NOT IN` it. `a!=x|y` stays "neither x nor y". Empty values keep their meaning on both kinds of field: `summary!=` finds pages with a summary, and a mixed clause (`summary!=|Draft`) still compares the effective value, which already excludes empty pages because of the empty value.

**Consequences:** `!=` means the same on shared and translatable fields, and is served by an index. Selectors that relied on `!=` leaving out pages without a value now find those pages too; adding `field!=` (has a value) restores the old result. The Page Finder could now offer "is not" on translatable text fields, which it held back because of this difference.

## ADR-0122: The admin's path is chosen per site, checked, and never hides pages silently

**Status:** Accepted

**Context:** The admin's routes sit under `LYNX_ADMIN_PREFIX` (`lynx` by default), and pages are served by the fallback route, after every other route. A site about lynxes that gives a page the name `lynx` never shows it: the admin answers `/lynx` and nothing says so. The prefix could already be changed, but only by knowing the variable; an empty or malformed value was accepted and broke the admin or put its screens over the site's URLs. Some teams also want the admin at an address nobody finds by trying `/admin` or `/lynx`.

**Decision:** `AdminPath` (`@api`) is the one reader of the prefix: it trims slashes and accepts one or more segments of lowercase letters, digits, `-` and `_`, throwing `InvalidAdminPath` with what to set otherwise, so a bad value stops the app at boot instead of misrouting. Every route group, Fortify's settings and `lynx:superuser` read it. `lynx:admin-path` shows the address, or moves the admin to a given path or to `--random` (20 characters from `a-z0-9`, about 103 bits), by writing `LYNX_ADMIN_PREFIX` to `.env` the way `key:generate` writes `APP_KEY`; it refuses a path that a page uses, or has pages below, in any language. A core doctor check, `page-routes`, matches every page's URL against the app's routes and warns when a route other than the fallback answers it, so pages hidden by the admin, a module or the app's own routes are found on existing sites too.

**Consequences:** A site picks its admin path with one command, and the common mistakes are refused with a reason. A random path only keeps out scripts that try common addresses: Fortify names its sign-in route `login`, so guests sent to a sign-in by the app's own `auth` middleware see the admin's address, which the docs point out. Uppercase paths are refused, since routes are case sensitive and editors would type them wrong. The doctor check reads every active path, so on very large sites it takes a moment longer; it stays read only. Pages are not refused at save time when the admin hides them, since core does not know the admin; the doctor check and the command cover it.
## ADR-0123: The home screen is arranged in code, by key

**Status:** Accepted

**Context:** Sites wanted to change the admin's home screen: drop a button they do not use, move a module's panel up, or add a panel with data from another service. `HomeScreen` let modules and apps add stats, buttons and panels, but nothing could take out or move what Lynx or a module added, panels had no keys, and Lynx's own lists were written into the view. A screen where each user arranges their Home was considered and set aside for now: it needs stored layouts per user, a way back to the default, and an editing mode, for a page with a handful of blocks, and most sites have one developer who decides what editors see.

**Decision:** Every entry has a key; a panel's is the view's name unless `panel()` is given one. `remove($key)` and `move($key, $order, $column)` are recorded and applied when the screen is drawn, so they work whichever provider runs first, and a key nothing has changes nothing. Lynx's lists of drafts, scheduled changes and recent changes are panels registered by `HomeDefaults` (`drafts`, `upcoming`, `recent`), and panels go in a main or a side column; the side column's place is left to the main column when it is empty. Who sees an entry stays with its `visible` closure. The docs show a panel with data from another service as a lazy Livewire component with a cache and a timeout, tested as a fixture, rather than a `lazy` option on `panel()`.

**Consequences:** Arranging Home is the developer's job, done in a service provider; editors cannot arrange their own. A layout per user can later be added on top of the same keys, as one more layer applied after the developer's. A module panel registered with an order of 20 or less now shows among Lynx's lists instead of below them. `panels()` returns Lynx's lists too, since they are panels now.

## ADR-0124: SQLite is the recommended database for most sites

**Status:** Accepted. Amends ADR-0013.

**Context:** ADR-0013 targeted PostgreSQL first, and the installation guide recommended it. Since then the corporate starter rendered 70 to 100 pages a second on SQLite and 44 to 64 on PostgreSQL on the same 2 core server, the search index uses full text search on both databases (ADR-0054, ADR-0119), and shared hosting, which offers no PostgreSQL, became a supported target. Most Lynx sites are read far more than they are written.

**Decision:** The docs recommend SQLite for most sites and PostgreSQL for sites with many writes at the same moment, several web servers, trigram indexes (ADR-0061), or PostgreSQL extensions the app uses for its own features (PostGIS, pgvector). `getting-started/database.md` explains the choice. Both databases stay fully supported: every feature works on both and CI runs the suite on both. MySQL and MariaDB stay unsupported for the reason ADR-0013 gives, non-transactional DDL.

**Consequences:** New sites start with no database server. Sites that outgrow SQLite move to PostgreSQL through a content package. Features that need PostgreSQL stay opt in, as trigram indexes are.

## ADR-0125: Temporary links to files, and form components that answer with them

**Status:** Accepted

**Context:** A site wanted gated downloads: a visitor fills a form and gets a private report in the language they choose. Private files (ADR-0077) follow their page's rules, so a visitor who may open the page gets every file, and one who may not gets none; nothing handed one file to one visitor. The form component sent its submission and dropped it, so a subclass could not answer with something made from it, and a choice always offered every option of the form, so a report in English only still offered three languages.

**Decision:** `Pagefile::temporaryUrl($expires)` gives a link signed with Laravel's `temporarySignedRoute` to `/lynx-media/signed/{page}/{file}`, which serves the file as a download without asking the page's rules: the signature is the grant. It works for public and private files. The form component gets two protected hooks: `fieldOptions(FormField)` narrows a choice's options, and the narrowed fields are the ones shown and validated before sending, since the action validates against the form's full definition; `afterSent(Submission)` runs once a submission is stored, not for spam. `FormValidator::makeFor()` validates a list of fields, for components that changed them.

**Consequences:** Whoever holds a link can pass it on until it expires, so sites keep the time short. A narrowed choice is enforced by the component only; agents and other callers of `forms.submit` see the full options, as the form defines them. Mail and other slow work stay in `FormSubmitted` listeners, on the queue.

## ADR-0126: Temporary passwords, and an admin that sends no email

**Status:** Accepted (amends ADR-0041)

**Context:** Invitations (ADR-0041) are the only way to add someone to the admin, and the password reset is the only way back in after a forgotten password. Both send email. Some sites have no mail setup, or don't want the admin to send any, and their superusers had no way to add people or let them back in.

**Decision:** Two superuser actions, never agent tools: `users.create_with_temporary_password` creates a user with roles, and `users.temporary_password` replaces a user's password (not the caller's own). Both set a random 20 character password with both cases, digits and symbols, store only its hash, and return it once. A row in `lynx_temporary_passwords` marks the user until they choose their own: admin routes send them to `account/new-password` first, where the temporary password can't be kept, and after `lynx-admin.auth.temporary_password_days` (1) a listener on Laravel's `Validated` event refuses it at sign-in, with Lynx's screens or the app's. A password reset or an accepted invitation removes the row, and a temporary password ends a pending invitation. `lynx-admin.auth.mail` (`LYNX_ADMIN_AUTH_MAIL`) set to false hides invitations and leaves Fortify's reset feature out of Lynx's sign-in. Invitations and `users.create_with_temporary_password` share their input and the creation of the user.

**Consequences:** Adding a user, by invitation or temporary password, needs at least one role: a user without one could not open the admin, and since ADR-0127 nothing gives them one later by itself. The superuser sees the password and passes it on, so it is only as private as that channel; the short life and the forced change limit the damage. The password is never in the activity log, which records only that a user was created or given one. It needs the user model to be Eloquent, as invitations do. A user who never signs in keeps an expired temporary password that signs in no one until a superuser gives a new one.

## ADR-0127: No open mode, and no role for people who sign up

**Status:** Accepted (supersedes the open mode of ADR-0033 and ADR-0078, and ADR-0073)

**Context:** ADR-0078 closed Lynx by default but kept `LYNX_OPEN_UNTIL_ROLES`, which gives every signed in user every right until any user holds a role, for local work. One environment variable set on a server, or copied from a local `.env`, would hand the admin to the first person who signs in or signs up. ADR-0073 created the `guest` role mostly to close that hole on sites with registration. A user without a role already has no rights, so the role adds nothing else by default.

**Decision:** The setting is removed. A user without a role has no rights, and no configuration changes that. Lynx's own test suite, whose tests act as users without roles, opens the checks with `Authorizer::openForTests()`, which throws unless the app runs unit tests. Install no longer creates `guest`, and `lynx.permissions.registration_role` and its `Registered` listener are removed. Sign-up exists only in an app's own registration code (Lynx's admin has none: staff come by invitation or temporary password), so that code assigns a role when it wants one. A role in reviewed code is safer than one in an environment variable, which could hand anyone who signs up a role that opens the admin. Existing `guest` roles and assignments stay: they grant nothing.

**Consequences:** A new site is unusable until a superuser exists, which `lynx:install` and `lynx:superuser` provide. Apps that set `LYNX_REGISTRATION_ROLE` assign that role in their registration code. The Users screen shows sign-ups without a role.

## ADR-0128: Two-factor authentication required for the admin

**Status:** Accepted

**Context:** The admin offers two-factor authentication (ADR-0041) but leaves it to each user. A stolen password then opens the admin. Sites with a members area share one users table and one sign-in between staff and members, and requiring it of members would cost sign-ups and support for no gain to the admin.

**Decision:** `lynx-admin.auth.require_two_factor` (`LYNX_ADMIN_REQUIRE_TWO_FACTOR`, default true) makes every admin route send a signed in user without confirmed two-factor authentication to Account security, which shows why and hides **Turn off**. It runs after the temporary password check (ADR-0126), and only on admin routes, so members who never open the admin are not asked. It applies only when it can be met: Lynx manages accounts, Fortify's feature is on, and the user model uses `TwoFactorAuthenticatable`; otherwise `lynx:doctor` warns. A passkey does not meet it, since the password still signs in alone. `users.reset_two_factor` (superusers, not an agent tool) and `lynx:two-factor:reset {email}` turn it off for a user who lost their phone and recovery codes.

**Consequences:** Existing admin users set it up on their next visit, with no grace period. Local work sets the variable to false. A superuser who loses both phone and codes needs the server. Lynx's own tests turn the requirement off except where they test it.

## ADR-0129: A password change ends the user's other sessions

**Status:** Accepted (amends ADR-0126 and ADR-0128)

**Context:** A security review found that changing a password kept the user's other sessions and "Remember me" cookies signed in. A superuser gives a temporary password when someone forgot theirs or may have been broken into, so the second case was not covered. It also found that Livewire runs only a fixed list of middleware on component updates, which left out Lynx's `RequireOwnPassword` and `RequireTwoFactor`: the search and link dialog on Account security worked for a user without two-factor authentication. And sign-in attempts were limited per email and address only.

**Decision:** Use what Laravel has instead of code of our own. Every admin route carries Laravel's `AuthenticateSession`, after the app's middleware and not in `lynx-admin.middleware`, so a published config cannot drop it. It compares the password hash stored in the session and in the remember cookie with the user's current one on each request, so any password change, wherever it happens, ends every other session, and the request that changed it keeps its own. Lynx does not change remember tokens or call `logoutOtherDevices()` itself. `AuthenticateSession`, `RequireOwnPassword` and `RequireTwoFactor` join Livewire's persistent middleware. `RequireOwnPassword` signs out a user whose temporary password expired, for apps whose sign-in does not fire `Validated`. The `lynx-login` limiter adds 30 attempts an hour per email from any address.

**Consequences:** Users signed in on several devices sign in again after a password change, which is the point. Resetting two-factor authentication changes no password, so it ends no session; the docs say to give a temporary password as well when a phone may be stolen. The hourly limit lets someone who knows an email keep its owner out for up to an hour; the review judged that better than unlimited guessing from many addresses. A user without a password hash is skipped by `AuthenticateSession`; Lynx always sets one, an unusable one for invited users.

## ADR-0130: The core stays under the MIT license

**Status:** Accepted (confirms ADR-0018)

**Context:** Before 1.0, Apache 2.0 was weighed against MIT for the core packages. Apache 2.0 adds an explicit patent grant with patent retaliation, terms for contributions, a clause saying the name is not licensed, and a NOTICE file; it also asks anyone who redistributes changed files to mark them. Every commit so far has one human author, so either change would be free today.

**Decision:** Keep MIT for every `lynxcms/*` package and for the starters. Of Apache 2.0's additions, only the patent grant is not already covered: GitHub's terms put contributions under the repository's license, MIT grants no rights to the name either, and MIT already requires keeping the copyright notice. MIT is the Laravel ecosystem's norm and asks less of people who change or pass on Lynx. It is also the reversible choice: MIT code, contributors' code included, can move to Apache 2.0 later, while Apache 2.0 code cannot be relabelled MIT without its contributors' consent.

**Consequences:** No relicensing work before 1.0. If an enterprise customer's legal review asks for a patent grant, moving to Apache 2.0 stays open, for releases from then on. The name "Lynx CMS" is protected by registering it and publishing a trademark policy, which no software license does. Paid modules keep their own commercial terms (ADR-0018), and those still need the legal review before launch.

## ADR-0131: The public API before 1.0

**Status:** Accepted (refines ADR-0018's `@api` rule)

**Context:** Every class marked `@api` becomes a promise at 1.0. An audit (`documentation/reports/api-audit-2026-11.md`) found 300 `@api` classes, many used by no app, module or guide, and 178 classes with no marker at all.

**Decision:** A class is `@api` when apps or third-party modules need it: actions (their class and input are how apps write), what `lynx()` and its module APIs return, events, exceptions the API throws, interfaces and abstract classes meant for extension, admin hooks for modules, and every class the docs or the guides' code use. Everything else is `@internal`: service providers, Livewire screens, Blade component classes (the tag is the API), Artisan commands (the command is the API), agent and MCP tools, field type and formatter classes (their names are the API), and the inner parts of features. Lynx's own packages may use each other's `@internal` classes, because they are released together under one version. A public method that only Lynx's packages need is marked `@internal` on the method; parameters that exposed internal classes are removed, as `$scope` was from `PagesApi::find()` and `childCounts()`. `tests/Unit/PublicApiTest.php` fails on a class without a marker and on the guides' code using an `@internal` class.

**Consequences:** 127 classes became `@internal`, 81 unmarked classes (the actions, `lynx()->ai()` and its run) became `@api`, 97 unmarked classes became `@internal`, and `IntegerConverter`, which the custom field type guide uses, became `@api`, leaving 255 `@api` classes. Apps that used a demoted class keep working until 1.0 but lose the promise; the starters still use three classes that were `@internal` before the audit (`Branding`, `Trash`, `Unlocks`), to be replaced or made public before 1.0. The backward compatibility check in CI comes next, on this list.

## ADR-0132: A backward compatibility check of the public API

**Status:** Accepted

**Context:** ADR-0131 made the public API explicit: 255 classes marked `@api`. A promise is only as good as its enforcement: without a check, a refactor can remove or change a public method without anyone noticing until an app breaks.

**Decision:** CI runs Roave's backward-compatibility-check on every pull request, comparing the pull request with its base. It checks every class and method not marked `@internal`, so the markers decide what it guards, and it reports removed classes and methods, changed signatures and classes that became `@internal`. The tool lives in `tools/compat` with its own Composer lock, apart from Lynx's dependencies. `composer compat` runs it locally against the latest release, for writing release notes. Until 1.0 the check only reports, in the run's summary and as a warning: 0.x releases may break on purpose, and each break goes in the release notes. At 1.0, `COMPAT_BLOCKING` in the workflow becomes true and a break fails the pull request. Livewire moved from the root `require-dev` to `require`: the tool installs only `require` dependencies, and the admin's Livewire components could not be compared without it.

**Consequences:** Every pull request shows what it breaks in `@api` code. After 1.0 a break needs a new major version, or a deprecation first. The check costs about a minute per pull request. It does not see behaviour changes behind an unchanged signature; tests and release notes cover those.

## ADR-0133: No PHP template classes (`lynx:sync`)

**Status:** Accepted (amends ADR-0014)

**Context:** ADR-0014 kept Lynx 1's PHP template classes as an option: one class per template, compiled by `lynx:sync` into the same actions as everything else. They were never built. Meanwhile the schema can be defined in the admin, in YAML files (`lynx:schema:export` and `lynx:schema:apply`), with PHP calls (`lynx()->fields->add()`, `lynx()->templates->add()`) and with schema drafts (`lynx:draft`), and PHPStan already knows each template's fields through the `FieldProperties` extension.

**Decision:** Drop PHP template classes and `lynx:sync` from the plan. Lynx 1.0 ships without them.

**Consequences:** One fewer source of truth to keep in step with the others, and no rule needed for when a class and a YAML file disagree. Developers who prefer classes write a seeder or an install action with PHP calls. If Lynx 1 users ask for classes, they can come after 1.0 as a module on the same actions, without breaking anything.

## ADR-0134: Versioning, deprecations and support from 1.0

**Status:** Accepted

**Context:** 1.0 is a promise to apps and modules. ADR-0131 and ADR-0132 define the public API and check it in CI; what each version number means, how long a version is supported and how a `0.x` site reaches 1.0 were not written down. `SECURITY.md` supported only the latest `0.x` release.

**Decision:** From 1.0, Lynx follows semantic versioning for its public API: `@api` classes, and also action names and input, events, Artisan commands, configuration keys and environment variables, Blade components and directives, field type and formatter names and settings, the selector syntax, and the URLs Lynx serves. The admin's screens are not public API. Minor and patch releases may add migrations, run with `lynx:migrate`, never needing code changes. Deprecations are announced in a minor release and removed in the next major. The latest minor release of the current major gets bug and security fixes; the previous major gets security fixes for 12 months after the next major. Dropping a PHP or Laravel version happens only in a major release. Sites reach 1.0 from the latest `0.x` release; the `0.x` migrations are kept, not squashed, so every site upgrades the same way. `docs/prologue/versioning.md` is the public statement of this policy.

**Consequences:** One maintainer backports security fixes to two majors at most. Breaking the admin's look or wording needs no major release, so its screens can keep improving. Squashing migrations later stays possible at a major release.

## ADR-0135: Notices when two-factor authentication changes

**Status:** Accepted (follows the security review, finding L1)

**Context:** Requiring two-factor authentication on first use means whoever signs in first with the password sets it up (security review, L1), and a superuser's reset or `lynx:two-factor:reset` removes it without the user knowing. Superusers also had no way to see who had not set it up.

**Decision:** Lynx listens to Fortify's `TwoFactorAuthenticationConfirmed` and `TwoFactorAuthenticationDisabled` events, which every way of turning it on or off fires, and emails the user a queued notice saying what changed, when, and to tell the site's manager if they did not make the change. It emails only users who can open the admin (an app's members hear from the app) and only when the admin sends email. While two-factor authentication is required and can be set up, the Users screen marks admin users without it.

**Consequences:** A user learns within minutes when someone else set up or removed their second factor. Sites with a queue need a worker for the email; without one Laravel sends it at once. The notice can't say who made the change, as Fortify's events don't carry it.

## ADR-0136: The admin meets WCAG 2.1 AA, checked with axe

**Status:** Accepted (amends ADR-0084 for the dark accent)

**Context:** The first accessibility review of the admin (`documentation/reports/accessibility-review-2026-11.md`) found six failing rules on every screen with axe-core, among them a search field with an attribute it may not have, a sidebar list broken by its `<nav>`, tabs without panels, and text too pale in dark mode. BlatUI's dark primaries are fills for buttons, and read at 2.4:1 as text; ADR-0084 also reused a site's light accent unchanged in dark mode.

**Decision:** The admin targets WCAG 2.1 level AA. Primary-coloured text uses `--lynx-primary-text`, which under `.dark` mixes the primary with white, for every BlatUI theme; buttons keep `--primary`. A site's accent without a dark one is lightened for dark mode until it reaches 4.5:1 on the dark background (`AccentContrast`); light mode keeps the site's colour, and a dark accent the site sets wins. Language tabs use real tab panels; tabs whose content the server renders elsewhere omit `aria-controls`. The layout has a skip link, the notice area is a live region from the start, and `<html>` has an opaque background so checkers measure what people see. `tools/a11y` repeats the axe scan in light and dark mode.

**Consequences:** No axe failures on the 31 screens reviewed, in either mode. Branded sites look slightly lighter in dark mode when their accent is dark. Two findings stay open: error messages tied to their fields, and notices that pause on hover or focus. A screen reader pass by a regular user is still to do.

## ADR-0137: Browser tests of the sign-in flows

**Status:** Accepted

**Context:** Signing in to the admin crosses Lynx's screens, Fortify, Livewire and Alpine: the password, two-factor setup and challenge, temporary passwords and invitations. Livewire and HTTP tests checked each step alone, never the screens a person goes through. The first run in a browser found a step no HTTP test had put together: with two-factor authentication required, a first sign-in asked for the password twice in a row, once to sign in and once more for **Account security** (`password.confirm`).

**Decision:** The sign-in flows have browser tests in `tests/Browser`, with Pest's browser plugin, which drives Chromium through Playwright against the testbench app in the same process. They are a test suite of their own, outside the default run, so `composer test` needs no browser; `composer test:browser` runs them. CI runs them in a job of their own, only when a change touches the sign-in flows (Lynx's sign-in code, screens and wording, `composer.json`, or the browser tests), and keeps the screenshots of failures. Tests act as a person does: by field names, button text and what the screen says. The password typed to sign in counts as just confirmed (`ConfirmPasswordOnSignIn`, on Laravel's `Validated` event), so password-confirmed screens open within `auth.password_timeout`; a remember cookie or a passkey confirms nothing.

**Consequences:** Most changes skip the browser job; one that touches sign-in adds a job running beside the others, so it does not lengthen the run. A new Fortify or Laravel release comes in without any file changing (no `composer.lock` is committed), so it does not run the job: run `composer test:browser` before a release that updates them. Playwright is a development dependency in a root `package.json`. Browser tests cover flows, not rules: the rules stay in the HTTP tests, which are faster and say more when they fail. More flows (the page editor, the page tree's move mode) can join the suite when they need a browser to be trusted.

## ADR-0138: Error messages tied to their fields in the browser

**Status:** Accepted (follows ADR-0136)

**Context:** The accessibility review found about 130 error messages in the admin shown next to their fields but not tied to them: a screen reader on the field said "invalid" without the reason. Only about 50 of the fields even marked themselves invalid. Adding an id to each message and `aria-describedby` to each field by hand touches every form, and a field and its message are often in different partials, with keys made at runtime (`values.title`, `settings.types.3.label`).

**Decision:** Each admin error message carries `data-error-for`, the error's key, and `role="alert"`. A script in the admin (`js/field-errors.js`) ties each message to the controls bound to that key in the same Livewire component or form (by `wire:model`, or by `name`), giving the message an id and the controls `aria-describedby` and `aria-invalid`. A key for a whole field ties the message to each of its inputs. Livewire's morph sets attributes back to the server's HTML, so the script derives the wiring again on page load, after each Livewire morph (its `morphed` hook) and after `wire:navigate`, and at no other time. Errors only appear with a page load or a server response, so it misses none. It does no work while someone types, and no other script's writes set it off: a page-wide observer, as BlatUI's `keepWired` uses, could trade writes with BlatUI's tooltip triggers, which set a fixed `aria-describedby`, once per frame, forever. The public form fields of the Forms module, which run without the admin's script, point to their message in the HTML.

**Consequences:** One attribute per message, which modules use the same way (documented under Extending the admin). The links exist only once the script runs; the admin needs JavaScript anyway. Browser tests check them, after a Livewire update too. A message whose key matches no control (a whole list's error) stays unlinked, announced by its alert when it appears.

## ADR-0139: PHPStan keeps database writes in actions

**Status:** Accepted

**Context:** Every write is an action (AGENTS.md rule 1): actions check permissions and record activity, and MCP and AI tools come from them. A write anywhere else skips the permission check and the activity log. `ArchitectureTest` kept the `DB` facade to an allowed list of classes, but Pest's architecture tests see which classes a class uses, not which methods it calls, so Eloquent writes (`$model->save()`, `Model::create()`, `->delete()`) were not checked at all. Telling `$model->delete()` from `lynx()->pages->delete($id)`, which runs an action, needs types.

**Decision:** A PHPStan rule (`tests/PHPStan/WritesOnlyInActionsRule.php`, identifier `lynx.writeOutsideAction`) reports write methods called on an Eloquent model, an Eloquent or query builder, or a relation, unless the call is in an action, in a class implementing one of Fortify's write contracts (the app's users are written the Fortify way, and the app may replace those classes), or in a class or namespace listed under `lynxWriters` in `phpstan.neon.dist`, each with its reason: the storage the actions call, the activity log, data built from content (caches, menu builds) and the user records the user actions write. It is an allowed list, not a list of forbidden places (controllers, Livewire, MCP tools): a helper class called from a controller would get past a forbidden list, and so would any new kind of class.

**Consequences:** The first run found two writes outside actions, both on sign-in screens: Account security saved the password itself (now Fortify's `UpdatesUserPasswords`), and the new password screen deleted the temporary password itself (now Laravel's `PasswordReset` event, as invitations do). The rule checks where a write is made, not who called it: a listed class called from a controller is not caught, so the list stays short and each entry says who calls it. Code PHPStan does not analyse is not checked: `packages/forms/src` was missing from its paths, and was added with its 40 type errors fixed.


## ADR-0140: Labels follow the language the editor reads the admin in

**Status:** Accepted. Amends ADR-0043 and ADR-0097, where labels followed the content language.

**Context:** An editor who reads the admin in Portuguese saw field labels in the site's default language, next to Portuguese wording, even when the site had stored Portuguese labels. The admin collected label translations that nobody reading in that language could see. The editor's language can also be one the site does not publish in, such as an Estonian editor on an English site. Label maps can't hold it, because their keys are site languages (ADR-0043). Modules install their labels in English only, as plain text.

**Decision:** The editor's language is a locale. `UseAdminLocale` gives it to `EditorLanguage`, a scoped service that outside the admin holds nothing. The text of a field or template that an editor reads (label, description, notes, placeholder, tab, section, group, block type, add button, choice option) reads through `LocalizedText::forField()` or `forTemplate()`, in this order:

1. The text stored in a site language that matches the locale: the same code or locale, then the bare language, then another variant of it.
2. A translation in a language file, looked up by the text in the site's default language: the app's `lang/{locale}/lynx-schema.php`, then, for the templates and fields a module installs, the module's `schema.php`. The module registers its namespace with `SchemaWording`. The app's fallback locale plays no part, as an English editor needs no file for English labels.
3. The text as it read before.

`LocalizedText::in()` keeps its meaning and stays the read for content: choice labels on pages, `@string`, forms, image descriptions. An explicit read was preferred to changing `in()` for admin requests, where a static export or a cache warming renders public pages. Installing module labels as maps was rejected: every existing site would need an update, and a site without the language could not hold it. Adding module labels to `lang/{locale}.json` was rejected, as they would change the app's own `__('Title')` (ADR-0097).

**Consequences:** No database change. Stored text, schema files, exports and sync are untouched, so code that compares a label with what it installed must compare `labels()->toStorage()`. A label the site rewords no longer matches a file's key and shows as the site wrote it. Two texts that read the same in the default language share a translation in a file. Values keep the content language, so a choice input offers Portuguese options while a table cell shows the stored value's label in the content language. Form titles and questions are content and stay in the content language too.

## ADR-0141: Messages people read come from language files

**Status:** Accepted. Amends ADR-0097, where validation errors from actions stayed in English.

**Context:** An editor who reads the admin in Portuguese met English when a save was refused ("Title is required in the default language."), beside Laravel's own rule messages in Portuguese when the app has them. With labels in the editor's language (ADR-0140), the two mixed in one sentence. Laravel's messages also named every field "value", because Lynx validates each value under that key. ADR-0097 kept the errors in English because agents and MCP clients read them too. Laravel's rule messages already reach those in the app's language.

**Decision:** Validation errors, rule failures, the errors of repeater and matrix items, and authorization refusals read from language files, under the package's namespace (`lynx::validation.page.locked`), in English, Portuguese, and Spanish. The English text does not change. A field's value is validated with its label as the attribute. Packages move over in steps: `tests/Unit/HardcodedMessagesTest.php` counts the messages still typed into each package's code and refuses a higher count.

Staying in English: action descriptions, console output, the change summaries the activity log stores, and errors for developers (selectors, drafts, exceptions about code).

**Consequences:** A refused save reads in one language when the app has that language's `validation.php`; Lynx does not ship Laravel's messages. MCP over HTTP, queues, and Artisan read the messages in the app's locale, and an agent run from the admin reads them in the editor's. Code that must recognize a refusal compares the error's key or the exception, as the text is no longer fixed.

## ADR-0142: Selector errors read from language files

**Status:** Accepted. Amends ADR-0141, which left selector errors in English as errors for developers.

**Context:** Page Finder and the admin's search show a `SelectorException` message to editors, beside wording in their language.

**Decision:** Selector errors come from `lynx::selectors`, in English, Portuguese, and Spanish. `HardcodedMessagesTest` refuses a typed one. Field names, operators, and selector words (`include=all`, `today`) are code and stay as they are in every language.

**Consequences:** A selector error in a log or on the command line reads in the app's locale. Code that catches a `SelectorException` should not compare its text.

## ADR-0143: Core's remaining messages and permission labels read from language files

**Status:** Accepted. Continues ADR-0141.

**Context:** ADR-0141 moved the messages about saving a page. The rest of core (fields, templates, roles, drafts, schedules, the trash, and refusals for lack of a permission) was still typed in English. Refusals also name the permission by its label, which only the role form translated, from the admin's language files.

**Decision:**

- Core has three files beside `selectors.php`: `validation.php` for pages and content, `structure.php` for fields, templates, and languages, and `access.php` for roles, permissions, and providers. `HardcodedMessagesTest` no longer allows a typed message in core, and `TranslationFilesTest` checks that every key the code names exists.
- Permission labels move from `lynx-admin::roles.permissions` to `lynx::permissions`, and `PermissionRegistry::label()` reads them for the role form and for refusals. A label another module registers is translated by the app, as before.
- Setting names, flags, and action names (`width`, `show_if`, `pages.restore`) are code and stay as they are in every language.

**Consequences:** An app that overrides `lynx-admin::roles.permissions` moves those lines to `lynx::permissions`. A draft error (`lynx:draft`) that quotes a template rule reads that rule in the app's locale.

## ADR-0144: Modules translate their messages and the labels they install

**Status:** Accepted. Completes ADR-0140 and ADR-0141.

**Context:** The modules and the admin still typed 56 messages in English, and the fields and templates they install (menus, forms, SEO, sitemap, page cache, page passwords) showed English labels on a Portuguese or Spanish admin. Installing labels per language would write text a site never asked for and would miss languages added later.

**Decision:**

- Each package keeps its messages in `lang/{locale}/validation.php`. `HardcodedMessagesTest` refuses a typed message in every package.
- Each module registers the fields and templates it installs with `SchemaWording`, and ships `lang/{pt,es}/schema.php`: the English text it installs, then its translation. Core does the same for what `InstallLynx` and the trash create (Title, Home, Simple page, Page not found, Trash).
- `ModuleLabelsTest` installs every module and requires a translation of each text, with no stale line.

**Consequences:** No database change. Existing sites read the labels translated after an update, and text a site reworded stays as the site wrote it. Rewording an installed label in English means changing its key in each `schema.php`, and the old wording on existing sites then reads untranslated. Languages Lynx does not ship add `lang/vendor/{namespace}/{locale}/schema.php`.

## ADR-0145: An index of page references, with events, field addresses, and reports

**Status:** Accepted. Builds on ADR-0042 (fields that reference pages) and ADR-0009 (writes are actions).

**Context:** `Backlinks` finds the pages that reference a page by querying every field whose type references pages and joining the results in a union. Repeater and matrix fields search their stored data once per page. The trash warning checks a whole branch this way, 100 pages per query, so a large branch costs hundreds of queries. The scan also counts values of fields a template no longer uses, and it cannot say where in the referencing page the reference sits. Editors will get a References tab on templates and links from the site to the field that holds a value, and both need that location.

**Decision:**

- **Index table.** `lynx_page_references` holds one row per reference:
  - `page_id`: the page that holds the reference. It has the same name as in the field tables (`lynx_f_*`).
  - `linked_page_id`: the page it points at. "Reference" names the relationship in code and "linked" names the target, and the admin's wording ("Linked from", "Broken links") uses the second. No other name is added for either.
  - `field_id`, `language_id` (null for shared fields), `item_key` (the ULID key of a repeater or matrix item, null outside items), `kind` (`field`; `text` is reserved for rich text), and `sort` (the order inside the field).
  - `created_at`, nullable. Rows the migration or a rebuild creates have null, which means unknown.
  - An `id` primary key, so a sync deletes or reorders exactly the rows that differ.
  - Indexes on `(linked_page_id)` and `(page_id, field_id)`.
  - No user or update columns. A row is only added or deleted, so the history of who changed a reference lives in the activity log.
- **Kept in step by the value writer.** The writer that stores field values (`ValueWriter` and the item writers) updates the page's rows in the same transaction, for fields the page's template uses. Values of fields a template no longer uses are not indexed. Drafts are not indexed. Publishing a draft updates the rows. The rows come from `ReferencesPages::pageIds()`, so field types from modules and apps are indexed without changes.
- **Rebuild.** The action `page_references.rebuild` (permission `page_references.manage`, command `lynx:page-references:rebuild`; action and permission names use underscores like `page_cache.*`) compares the index with the stored values and changes only the rows that differ. Its dry run lists the differences. A migration fills the table on existing sites.
- **Reads.** `Backlinks` reads the index. `lynx()->pages->backlinks()` keeps its signature, including `branch: true`.
- **Changes and events.** A save that adds or removes references lists them in its change (`references: {added, removed}`, each with `linked_page_id`, `field`, and `item_key`), which the activity log keeps with the actor. The save queues one `PageReferencesChanged(pageId, added, removed)` event with the same entries, dispatched after commit like every action event. A template switch that changes references records and dispatches them the same way. The event implements `ContentChanged`, and its affected pages include the linked pages, since a page may show what links to it. Changes to a template's fields resync its pages without events.
- **Muting events.** `lynx()->withoutEvents(Closure $callback, array $events = [])` mutes every event, or the listed classes, while the callback runs. The `ActionRunner` drops muted events when they are queued, so a large import does not hold them in memory. Muting nests and ends when the callback ends, also on an exception. Muted events that implement `ContentChanged` fold into one `ContentBatchChanged` event, dispatched after commit, whose `affectedPages()` is the union of their pages, or null past a limit. The page cache, search, and stored menus listen to `ContentChanged` and stay correct. Work that keeps Lynx's stored data correct must not depend on an event that can be muted. It registers as an upkeep listener (`EventMuting::upkeep()`), which the `ActionRunner` calls after commit for every event, muted or not, before the app's listeners: sort keys, the search index, media folders, and page password caches. Muted events without upkeep listeners are dropped when queued. (Amended while building it: moving `UpdateSortKeys` into the actions would have left the search index and media folders, and media folders must be deleted only after commit.) `ImportContent` takes an `events` input (default true) that wraps the import in `withoutEvents()`, so agents can run quiet imports too.
- **Field addresses.** `/lynx/pages/{id}/edit?field={name}&item={item key}&lang={code}` opens the editor on the tab that holds the field, in that language, and scrolls to the field or item. An unknown field or item, or one the user may not edit, opens the editor as usual. A helper builds the address through `route()`, since the admin's path is configurable. The item is given by its key, which stays the same when items are reordered.
- **References tab.** A tab lists the pages that reference the page being edited. It is its own Livewire component, loaded when the tab opens, not refreshed when the page is saved, paged 50 rows at a time, and limited in its query to pages the user may view. It replaces the Settings tab's "Linked from" panel. Each row opens the referencing page at the field and item that hold the reference. The tab does not remove references: editors remove them in the referencing page's editor, where its drafts, lock, and field rules apply. A count on the tab's label comes only with the index.
- **Reports screen.** The admin's **Page references** screen, under Settings, has reports (broken links, pages nothing links to, and the most linked pages) and the index's health, with the rebuild and its dry run. The rebuild runs as a queued job. The table, the event, the rebuild action, and the report queries live in core, because the value writer and `backlinks()` need the first three. (Amended while building it: a separate `lynxcms/page-references` module was planned, but it held only one screen that depends on the admin's internals, so it is part of the admin.) Core's `@internal` `Pages\PageReferences`, which turns an id, path, or name into a page id, is renamed `PageLookup` so the two names do not collide.
- **Rich text links.** The link dialog stores the page's id beside its address (`<a href="/pt/cursos/alpha/" data-page="42">`) while the address is still that page's or one of its sections. The HTML cleaner accepts `data-page` on links, whole numbers only. When a value is shown, `PageLinks` sets `href` to the page's current address in the language shown, keeping the section, and for visitors keeps only the link's text when they could not open the page: unpublished, trashed, gone, or hidden by the visibility rules, the same rule as page fields. (Amended while building it: the ADR first said hidden pages lose their links too, but page fields keep them, and a hidden page is still meant to be opened.) `TextareaType` implements `ReferencesPages` for rich text, so the index (`kind = text`, per language, inside items too), eager loading, page copies, and content packages handle these links like page fields. Import writes rich text first without its page ids, so a required field never blocks a page, then again with the keys mapped back. Adding `data-page` to existing links whose address matches a page is left for a later rebuild option.

**Consequences:**

- One indexed query replaces a union over every reference field, for backlinks, the trash warning, the References tab, and the reports. Each save writes a few more rows.
- The index is a copy of stored values. A write path that skips the writer would leave it out of step. A test compares the index with a full scan after every kind of write (save, import, revision restore, template switch, purge of orphaned values, field removal, delete), and the rebuild repairs a site.
- "Linked from" stops listing pages that reference a page only through a field their template no longer uses.
- Muting turns the listeners that keep stored data correct into upkeep listeners, which `Event::fake()` does not stop. Listeners that apps register for muted events do not run. `ContentBatchChanged` keeps caches correct.
- The work ships as separate pull requests, in order: the index (with `PageReferencesChanged` and the `PageLookup` rename), muting events, field addresses with the References tab, the reports screen, and rich text links.
- A button that removes a reference from the References tab is left out. If it comes, it is a core action (`pages.remove_reference`) that refuses when the referencing page has a draft, is locked, or would break its field's rules, removes one reference at a time, and never applies to rich text or menu items.

## ADR-0146: A builder form's structure travels as JSON, without its site's data

**Status:** Accepted (builds on ADR-0053 and ADR-0116)

**Context:** A complex form, with steps, groups, conditions and translated options, takes time to build in the admin, and agencies want to reuse one on another site. Forms built in the admin are pages under a hidden branch, with their fields as blocks of the `form_fields` matrix, so copying one meant rebuilding it or moving a content package. Code forms already travel with the app's code. Two ways were considered: a `forms` section in drafts (ADR-0116), and an export and import of one form. Drafts build schema in core, and core may not depend on the forms module, so a draft section needed a hook for modules to add sections, which is new public API for one module. Copying a form is also content transfer, where a draft is create-only schema building.

**Decision:** A form exports as JSON and imports as a new form.

- **Format.** `{lynx_form: 1, name, default_language, title, messages: {success, error, auto_reply}, fields: [{type, depth, enabled, values}]}`. Blocks are stored as the matrix holds them, minus their keys. Texts are keyed by language code, without fallback, and `default_language` names the source's default. `lynx_form` is the format's version, and an import refuses another.
- **Left out.** Recipients, the one answer per account setting and submissions belong to the site. Recipients are addresses, which shouldn't travel by accident.
- **Import.** `forms.import` (`forms.manage`) runs `forms.create`, then `pages.save` with the fields and messages, so the builder's own checks run and an import is a dry run like any action. The form is unpublished, since it has no recipients yet. A name the site has is refused, and `name` gives another. The source's default language texts become this site's default, and texts in languages this site lacks are dropped and listed in the result, since refusing the whole form for a missing translation helps nobody. A block value its type doesn't show is refused.
- **Entry points.** **Export** and **Import form** in **Forms › Forms**, `lynx:forms:export` and `lynx:forms:import`, and the action over MCP. `FormExporter` writes the format and is `@api`.

**Consequences:** One file copies a form between sites, and between environments. Drafts stay core-only. A later format change bumps `lynx_form` and keeps reading version 1. Updating a form that exists from a file is not supported: replacing its blocks would turn submissions' fields into orphans, so a copy gets a new name.

## ADR-0147: A person's form data is found by their email address, and forms can expire their submissions

**Status:** Accepted (builds on ADR-0053 and ADR-0146)

**Context:** Forms collect personal data: names, addresses, job applications. Under the GDPR a person may ask what a site holds about them and ask for it to be deleted, and data should be kept no longer than needed. The inbox searched one text at a time with `LIKE` on part of an answer, so "ana@example.com" also found "joana@example.com", and a search covered the statuses one filter showed. The daily purge deleted only old spam and archived submissions, so everything else stayed forever.

**Decision:**

- **By email.** `SubmissionFilter` takes an `email`: submissions where that address is a whole answer, matched as its JSON string with quotes, lowercased, and with `LIKE`'s wildcards escaped, since addresses often hold `_`. Matching an email field by name was considered and left out: forms name their fields freely (`email`, `your_email`, a field in a group), and the address in any answer is what the person asks about. `PersonalData` lists, exports (JSON, answers as the form asked them, file names) and deletes through `forms.delete_submissions`, so a deletion is one action with a dry run. **Forms › Personal data** needs `forms.manage`, as it reads across forms and deletes.
- **Retention.** A form keeps submissions for a number of days: the builder's `form_retention_days` field, `Form::retentionDays()` in code, or `lynx-forms.retention_days` for forms without their own. `forms.purge` deletes older submissions in any status through `forms.delete_submissions`, by whole days. Days were chosen over months for one unit everywhere; the help text gives 365 as a year.

**Consequences:** A data request takes one screen. A site that sets retention loses submissions for good on schedule, which is the intent; the setting is empty by default. The email match reads every row's JSON, as the inbox search does, which is fine for thousands and slow for hundreds of thousands (the search index could take it over). The builder field reaches existing sites when `forms.install` runs again.

## ADR-0148: Cloudflare Turnstile guards public forms when a site sets its keys

**Status:** Accepted (builds on ADR-0052)

**Context:** Public forms had Spatie's honeypot, its timing check and a rate limit per visitor. The timing check is useless on cached pages, where every visitor gets the time the page was rendered, and bots that fill visible fields slowly get past the honeypot. Captchas with puzzles cost real visitors. Cloudflare Turnstile checks visitors without a puzzle for most, needs a free account, and verifies a token on the server.

**Decision:**

- **Off by default.** Turnstile runs only when a site key and a secret key are set, as it loads a third party's script in visitors' browsers, which some sites must declare under the GDPR.
- **Keys in two places.** `.env` (`LYNX_FORMS_TURNSTILE_SITE_KEY`, `LYNX_FORMS_TURNSTILE_SECRET`), as Laravel keeps secrets, or a superuser saves them with `forms.save_turnstile`, the secret encrypted with the app key in Lynx's settings. Keys in `.env` win, and the admin then only says where they come from. An admin setting lets a site owner without server access turn it on; the cost is a secret in the database, unreadable without the app key, and lost if the key changes (Turnstile then stays off until it is saved again).
- **Checked in the form component**, after the rate limit and before the honeypot, by Cloudflare's `siteverify` with a 5 second timeout. A check that can't reach Cloudflare fails, so an outage doesn't open the form to bots; it is logged. `forms.submit` itself doesn't check, so code and MCP clients that submit on purpose aren't asked for a browser token.
- **One widget per form**, rendered explicitly with Alpine, its token handed to the component and reset after each check, as a token is good once.

**Consequences:** Sites with cached pages get a spam check that works. A Cloudflare outage stops form submissions on sites that turned it on. A Content Security Policy must allow `challenges.cloudflare.com`.

## ADR-0149: Locked fields are written only by code; display, locked and loading are separate settings

**Status:** Accepted (changes ADR-0063)

**Context:** Projects fill some fields from code, such as stock from a shop or text from another service, and editors must not change them. ADR-0063 gave a template's field the editor display `locked`, but it only hid the input in the page editor. `pages.save` didn't check it, so MCP clients and the agent wrote the field, and agents weren't told it was locked. Publishing a draft and restoring a revision rewrote every template field, so a value code had replaced on the live page came back from the draft. A field couldn't be locked on every template at once. ProcessWire's Presentation setting combines how a field starts (open, closed, closed when blank or filled), whether it is locked, and whether it loads only when opened; ADR-0063 offered locked as one display among the others, so a locked field couldn't also start closed.

**Decision:**

- **Two settings.** `editor` says how a field shows (`open`, new `open_collapsible`, `collapsed`, `collapsed_blank`, `collapsed_populated`, `hidden`), and `locked` says whether editors may change it. Locked combines with every display but hidden. `editor: locked` still reads, as open and locked; the template admin writes the pair.
- **Locked on the field or on a template.** A field's own `locked` setting applies on every template; a template's `locked` applies there. `TemplateField::isLocked()` answers for both.
- **Only code writes a locked field.** `pages.save` refuses a locked field's value unless the actor is the command line or the system (`LockedFields`). People, MCP clients and the agent are refused with a message on the field. Code in a web request, such as a webhook's controller, runs its save in `lynx()->actingAs(Actor::system(), ...)`. Roles that may edit a locked field were considered and left for a later ADR on field access per role. Hidden fields keep ADR-0063's rule: the editor doesn't show them, and code, MCP clients and the agent write them, as projects use them for values agents fill.
- **Drafts and revisions keep the live value.** `Revisions::saveInput()` leaves locked fields out unless asked, so publishing a draft and restoring a revision keep the live page's value and its origin. Drafts still copy the value, so editors see it.
- **Agents are told.** `describe_schema` marks locked fields, and `pages.save`'s description says saving them is refused.
- **A locked field can't be required:** editors could never fill it. Locking a field a template requires is refused, and so is requiring a locked one.
- **Load when opened (`lazy`).** A field that starts closed renders its value and input only once an editor opens it. Tabs already render their fields only when opened, so `lazy` applies to fields that start closed and is refused on others.

**Consequences:** Code that reads `TemplateField::editor()` gets `open` for a field stored as `locked` and checks `isLocked()` for the rest; `FieldDisplay::Locked` and `editor()` are deprecated. Sites that let agents fill locked fields must run that code as the system. A lazy field's input isn't in the page until it opens, so browser tests open it first.

**Amended 2026-11-25:** only superusers lock or unlock a field, on the field or on a template's row; `fields.update` and `templates.update` refuse other actors (core security review, L4).

## ADR-0150: Images and files can have a file per language

**Status:** Accepted (builds on ADR-0046 and ADR-0049)

**Context:** Projects need a different image or document per language: a banner with text in it, a brochure per language. Images and files fields are shared, with only each file's description per language, and ADR-0046's answer was to tag files with language codes and filter in the view with `MediaList::inLanguage()`. That doesn't work on a single-file field, which returns one file and no list, editors have to remember the tags, and the editor doesn't show which file is for which language. Making the field translatable, with a list per language as text has a value per language, was considered: it matches other translatable fields, but item tables keep one row per item shared by every language (language 0), so it would change how items are stored and need a migration of existing fields.

**Decision:** A field setting, `per_language`. Each file then has a `language` value, a code the site has or none for every language, stored with its other values, so no table changes. When the field is read, a language gets its own files and the files for every language, its own first; with neither, the files of the next language along its fallback chain, or none when the field's `fallback` is `none`. A single-file field returns the first. `max_files` counts per language. The editor shows a tab per language, as translatable text does. Each tab has two groups with their own upload zones: the language's own files, and the files shared by every language, shown in every tab. A tab named for no language (All files) was tried first and left out: it read as a language, and hid where shared files go. Each file's details have a Language select, and a tab without files of its own says what visitors see. `media.store` takes a `language`, and selectors test it (`hero.language=pt`). `inLanguage()` keeps ADR-0046's rule for tagged lists, reading a file's `language` as well as its tags.

**Consequences:** A banner per language is one field and no template code. Turning the setting on keeps existing files for every language, so nothing changes until an editor assigns languages. `getUnformatted()`, exports and content packages keep every file. A file per language and a description per language are separate: a Portuguese file still has a description in each language.

## ADR-0151: A field names the roles that may see it and the roles that may edit it

**Status:** Accepted (builds on ADR-0033 and ADR-0149)

**Context:** Roles grant permissions such as `pages.edit`, limited to templates and branches (ADR-0033), so a role edits every field of the pages it may edit. Projects need finer rules: a price only sales changes, internal notes editors shouldn't see. ADR-0149 locked fields for everyone but code and left roles for later. ProcessWire sets this on the field, in its Access tab. Two places were considered: on the field, a list of roles; or on the role, a list of fields in its `pages.edit` grant beside templates and branches. The grant fits the permission model, but answering "who may edit this field" means reading every role; the field answers it at once and leaves roles as they are.

**Decision:** A field setting, `access: {view: [roles], edit: [roles]}`. A field with the setting manages its access: only the roles it lists see or edit it, and with none listed, only superusers. A field without it is open to every role that may see or edit the page. A role that may edit a field may also see it. Superusers and the site's code (the console and the system) always may. A first version read an empty list as every role; it was changed before release to follow ProcessWire, where managing access is a choice of its own and an empty table means superusers only, which reads more plainly than an empty list meaning everyone.

- **Writes.** `pages.save` refuses a field's value from an actor whose roles may not edit it, with a message on the field; its other fields save. MCP clients and the agent are judged by their user's roles, or the anonymous role. Publishing a draft and restoring a revision leave such fields out, so they keep their live value and the rest publishes.
- **The admin.** The page editor leaves out a field the editor's role may not see, and shows read only a field it may see but not edit. The Finder doesn't offer fields a role may not see. The field form has an Access tab: a **Manage access for this field** checkbox, then a table with a row per role (superusers left out) and View and Edit columns; Edit is off for roles without `pages.edit`.
- **Agents.** `get_page` and the page results of actions leave out fields the actor may not see; `describe_schema` leaves them out and marks `editable: false` on those it may not edit.
- **Not visitors.** Templates read every field, so field access changes nothing on the site's pages. Selectors in code are not filtered by it either; selectors from agents and the Finder are, since ADR-0153.
- Role names are checked when saved; deleting a role leaves its name in a field's list, where it matches nobody.

**Consequences:** One field answers who sees and edits it. A role restricted from a field can still find pages by it with a selector in code it writes, which only developers do; the admin's own searches leave the field out, and since ADR-0153 agents and the Finder refuse it. Settings per template (a field open on one template, restricted on another) were left out until a project needs them.

**Amended 2026-11-25:** only superusers change a field's `access`; `fields.update` refuses other actors, and the field form leaves the Access tab out for them (core security review, L4).

## ADR-0152: Image sizes can be made in a queue

**Status:** Accepted (builds on ADR-0046; plan in documentation/plans/image-sizes.md)

**Context:** A size's file is written the first time its URL is printed (ADR-0046), so the first render of a page waits for every new size on it. A gallery printed through `<x-lynx-image>` makes three widths per image, dozens of resizes in one request, after every upload and focus change. Originals are at most 2560 pixels on a side (uploads are scaled down in the browser and by `media.store`), so each resize is quick, but together they can pass the server's time limit. A route that makes sizes when the browser asks (as Glide does) was considered and left out: the owner wants sizes made by the render or by a queue only, and the queue kept off unless a site runs a worker.

**Decision:** `lynx-media.queue_sizes` (`LYNX_MEDIA_QUEUE_SIZES`), false by default.

- **Off:** sizes are made during the render, as before.
- **On:** `Pageimage::url()` for a size whose file is missing dispatches `MakeImageSize` (unique per file name, one try) and prints a stand-in: the smallest existing size of the same original that keeps its aspect ratio and is at least as large, else the original. Each folder is listed once per request (`SizeQueue`, scoped). A size that can't be made is logged with its page and file, and the page keeps the stand-in.
- **Crops:** `<x-lynx-image>` with a width and a height adds `object-fit: cover` and the focus point as `object-position`, so a stand-in is cropped by the browser where the size will be.
- **Code that reads files:** `file()` still makes the size at once. The static site build turns the setting off while it renders, as it copies the files right after.
- **Page cache:** a page that printed a stand-in sets `LynxCms\Contracts\Pages\PageCache::SKIP` (new, `@api`) on the request, the attribute `@nocache` already set, so the cache stores the render after the jobs ran. The constant lives in contracts so media does not depend on the cache module.
- **Doctor:** `media-queued-sizes` warns when the setting is on and the queue connection is `sync`.

**Consequences:** With a worker, a page's first render no longer waits for its sizes; its first visitors see heavier images for a moment, and the page reaches the page cache one render later. Without a worker and the setting on, stand-ins stay: correct, only heavier, which the doctor check points at. Making sizes ahead of time (recording the specs each field uses and making them after an upload) waits for a site that needs it.


## ADR-0153: Selectors from outside the site's code run under a policy

**Status:** Accepted (changes ADR-0151 for agents and the Finder; from the core security review, documentation/reports/security-review-core-2026-11-25.md, M1 and M3)

**Context:** A selector can filter and sort on any field and name `include`, `status` and the `has_` terms. That is right for selectors a developer writes. Two other sources were found to leak content. An agent with `pages.view` whose role may not view a field (ADR-0151) could still filter and sort by it in `find_pages`: `salary>50000` or `sort=salary` tell the value through the total and the order, and `search*=` does the same when the field is indexed. An app that takes a whole selector from a visitor, as the selectors page showed, could be asked for unpublished pages (`include=all`) or for a field no template prints, one letter at a time (`notes^=a`). A per-field check inside the compiler was considered; checking the parsed selector before it compiles keeps the compiler as it is and covers clauses, OR groups, `sort=`, `join=` and page references in one place.

**Decision:** `LynxCms\Core\Query\SelectorPolicy` (`@api`), passed as `policy:` to `PageFinder::find()` and `lynx()->pages->find()`. It is checked against the parsed selector, and a refusal throws `SelectorException`, as an invalid selector does. A policy holds a field check (every part of `a|b` and every segment of `related.price` that names a field), terms it refuses, and caps on clauses, values and `start=`.

- `SelectorPolicy::forActor($actor)`: refuses fields the actor's role may not view, and `search` while such a field is indexed. Null when every field is open to the actor, so most sites pay nothing. The agent toolkit's `findPages` and the Finder's searches use it.
- `SelectorPolicy::forVisitors($fields)`: only the fields named (`search` when listed), no `include`, `join`, `status` or `has_` terms, at most 10 clauses, 20 values and `start=1000` by default.
- Selectors the site's own code writes run without a policy, as before.

**Consequences:** A restricted role can no longer find pages by a field it may not view, through agents or the Finder. Apps that pass a visitor's selector have one argument to make it safe, and the docs show it. A visitor's words inside the app's own selector are still the app's to escape (Visitor Input); the parked plan for bindings in selectors covers that case.
