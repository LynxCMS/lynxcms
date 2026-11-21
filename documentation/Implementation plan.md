# Implementation plan

The build runs in 16 phases: 10 for the MVP, 4 to reach a public release, and the admin's agent chat panel last, once every feature works without it. Each phase ends with something you can use and test by hand. Manual tests run against a **demo workbench**: the Testbench workbench app inside the repo, with a `cms:demo` seeder that grows phase by phase into a small course site in the default language and Portuguese.

## Status

| Phase | State | Notes |
| --- | --- | --- |
| 0 Setup | Done | Monorepo `lynxcms/*` (contracts, core, mcp, ai, admin placeholder), Pest, PHPStan level max (no baseline), Pint, Rector, GitHub Actions on PostgreSQL and SQLite, workbench |
| 1 Languages | Done | ActionRunner with hooks, dry runs and activity log; languages, chains, `lynx:install` |
| 2 Fields | Done | Six field types including `component`; runtime DDL rolls back on failure |
| 3 Page writes | Done | Names and paths per language with history, moves, deletes, template switching with orphans, settings pages |
| 4 Page reads | Done | `TranslatableValue`, batched `PageLoader`, eager loading, `lynx()->settings()` |
| 5 Selectors | Done | Effective-value filters and sorts, `lynx:find --sql` |
| 6 Front end | Done | Three locale middlewares, catch-all route, 301 for old paths, `@hreflang`, `@field`, `@lynxComponent`, `UsesLynxPage` |
| 7 MCP server | Done | Generated tools, read tools, prompt, resource, allowlist |
| 8 AI agent | Done | `CmsAgent` on laravel/ai, plan-then-apply with replay, `lynx:ask`, `pages.translate` with an LLM provider |
| 9 Spike | Measured | See ADR-0023: 50,000 pages × 3 languages, translatable filters and sorts 70–160 ms. A search index followed later (ADR-0054, ADR-0119, ADR-0120) |
| 10 Schema sync | Done | lynx:schema:export and lynx:schema:apply (uuid-keyed YAML, idempotent, --dry-run, --force). PHP template classes compiled by lynx:sync were dropped (ADR-0133): the schema is defined in the admin, YAML files or PHP calls. |
| 11 Livewire admin | Done | Page tree, menu builder, page editor (steps 1 to 8: inputs, rich text, page and component fields, Settings tab, unique status, review changes). Conditional fields (ADR-0030), templates and fields screens, template family rules (ADR-0032), text formatters (ADR-0031), roles and permissions (ADR-0033). Phase 11 is complete |
| 12 Lynx 1 modules | Done | Trash (ADR-0035), admin on BlatUI (ADR-0034), scheduled publishing per language (ADR-0036), edit locks (ADR-0037), revisions (ADR-0038), drafts with preview (ADR-0039), export, import and backups (ADR-0040), Laravel Boost guideline and skills. REST API and webhooks moved to v2 |
| 13 Translation | Built, not released | In the private paid module `lynxcms/translate`: DeepL, local models (OpenAI-compatible) and cloud models through `lynxcms/ai`, a glossary per language pair, a monthly budget in characters, whole pages and branches in the background, translations into drafts, badges for machine-written and outdated text (`source_hash` in core). No tag yet |
| 14 Public release | In progress | Released as 0.1.x on Packagist since 2026-10-22 (0.1.55 on 2026-11-21), with release notes and an upgrade guide for each. English documentation at lynxcms.org, three starters with guides, the first security review (`documentation/reports/security-review-2026-11.md`). Still to do: the 1.0 promise (see below), the private Composer repository and the first paid module's release, the license review, Portuguese documentation |
| 15 Agent chat panel | Not started | Planned after 1.0 |

About 3,900 tests pass on PostgreSQL and SQLite (and with a table prefix) in CI, and PHPStan at level max reports no errors, with no baseline. The code lives in the LynxCMS/lynxcms monorepo, split into read-only package repositories on every push. In code the API root is `lynx()` (facade `Lynx`) rather than `cms()`, commands are `lynx:*` rather than `cms:*`, and every table is prefixed `lynx_`.

&#91;embedded content: roadmap · 16 phases, MVP gate after phase 9\]

Phases run in order, left to right and top to bottom; each one only uses what the earlier ones built.

## The road to 1.0

Reviewed on 2026-11-19, after 0.1.52. Every phase before the release is built; what is missing is the promise itself and the release around it.

**Blockers**

1. **A smaller public API.** Done on 2026-11-19 (ADR-0131): 255 classes are `@api`, every class carries a marker, and a test checks it. Left: the starters' three uses of `@internal` classes.
2. **A backward compatibility check in CI.** Done on 2026-11-19 (ADR-0132): every pull request reports what it breaks in `@api` code; it becomes blocking at 1.0.
3. **A versioning and support policy.** Done on 2026-11-19 (ADR-0134, `docs/prologue/versioning.md`): semantic versioning of the public API, deprecations removed only in a major release, security fixes for the current major and the previous one for 12 months.
4. **The upgrade path from 0.1.x.** Done on 2026-11-19 (ADR-0134): sites update to the latest 0.x release, then to 1.0; every migration is kept, none squashed.
5. **The one `@deprecated` method removed.** Done on 2026-11-19: the admin's `SchemaOwners::register()` (ADR-0115).

**Before the release**

6. ~~The page cache check on Laravel Cloud.~~ Moved after 1.0 on 2026-11-20: most Lynx sites run on SQLite, on one server, so replicas are rare.
7. Browser tests of the sign-in flows (password, two-factor setup and challenge, temporary password, invitation). Done on 2026-11-20 (ADR-0137): `composer test:browser`, a job of its own in CI.
8. An accessibility review of the admin. Done on 2026-11-19 (ADR-0136, `documentation/reports/accessibility-review-2026-11.md`); the open findings fixed on 2026-11-20 (ADR-0138). A screen reader pass by a person moved to v2 on 2026-11-20.
9. Done on 2026-11-19 (ADR-0135). The follow-up of the security review: email users when two-factor authentication is turned on or reset, and show superusers who has not set it up.
10. ~~A decision on `lynx:sync`.~~ Decided on 2026-11-19: dropped (ADR-0133).
11. ~~The draft documentation.~~ Moved after 1.0 on 2026-11-19: drafts are hidden from the docs site, so they block nothing.

**Decisions for phase 14**

12. Portuguese documentation: before 1.0, or after.
13. The private Composer repository and the release of `lynxcms/translate`: with 1.0, or after.
14. The legal review of the paid modules' terms (ADR-0018). The core stays MIT (ADR-0130); register the name "Lynx CMS" and publish a trademark policy.

Not needed for 1.0: the headless API and webhooks (v2, `documentation/plans/headless.md`) and the agent chat panel (phase 15).

**After 1.0**

* **The bilingual website guide** (`docs/guides/bilingual-website/`, a draft): finish it as the website half of the meeting rooms starter, with its code moved into `docs/snippets` so the suite tests it, as the training site guide's is.
* **The page cache check on Laravel Cloud** the roadmap asks for: two replicas, Octane, `edge_ttl`. It matters for sites on a shared database (MySQL or Postgres) behind several servers.
* **The comparison page** (`docs/getting-started/compared.md`) stays a draft. Publishing it means checking every claim about the other products again and dating it, then checking it again at each minor release.

## Phase 0: Repository and quality setup

**Build**

- A `fresh lynx-evolved repository` with the `contracts`, `core`, `mcp`, `ai` and `admin` packages  split from one repository.
- CI running on PostgreSQL and SQLite, with PHPStan level 8, Pest (including architecture and type-coverage tests), Rector and Pint, carried over from Lynx 1.
- A Testbench workbench app and an empty `cms:demo` command.

**After this phase:** an empty but installable package with a green pipeline on both databases.

**Manual test**

1. `composer test` and `composer analyse` pass locally.
2. `vendor/bin/testbench serve` opens the workbench app in the browser.
3. Switch `DB_CONNECTION` between `pgsql` and `sqlite` and run the suite on each.

## Phase 1: Action runner and languages

**Build**

- System migrations, `ActionRunner` with `before`/`after` hooks and dry runs, the activity log.
- `Language` model, `LanguageContext` with fallback chains, `CreateLanguage`, `UpdateLanguage`, `DeleteLanguage`, `SetCurrentLanguage`.
- `InstallCms`, which creates the default language and the root page.

**After this phase:** a fresh install with a default language, and languages you can add, chain and remove at runtime, each change logged.

**Manual test** (in `php artisan tinker` on the workbench)

1. Run `cms:install`, then `cms()->languages->get('default')` shows code `default`, name Default and an empty prefix.
2. `cms()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default', prefix: 'pt')` returns a `Language`.
3. Add `pt-BR` with fallback `pt`, then check that `LanguageContext::chain()` in `pt-BR` gives `pt-BR, pt, default`.
4. Try a cycle (`default` falling back to `pt-BR`): a validation error, nothing saved.
5. Repeat step 2 for `es` with `dryRun: true`: you get the would-be change and no new row.
6. `select * from activity_events` lists each action with `actor_kind = cli`.

**Done when:** the chain, cycle, dry-run and hook tests pass, and the no-migration test shows the schema unchanged after adding a language.

## Phase 2: Fields and templates

**Build**

- The field-type registry with six types (`text`, `textarea`, `integer`, `toggle`, `page`, `component`), each with `normalize()`, `isHtml()` and a settings schema.
- `FieldSchemaManager` for runtime DDL inside the action's transaction.
- `CreateField`, `DeleteField`, `CreateTemplate` (with `from:`), `UpdateTemplateFields`, `DeleteTemplate`, and the `has_url` / `singleton` flags.

**After this phase:** you can define a site's content structure from code or Tinker, with tables created and dropped live.

**Manual test**

1. Create `title` and `body` (translatable), `price` (shared) and `related` (page), then a `course` template with them.
2. In `psql`, `\d field_title` shows `page_id`, `language_id`, `data`; `\d field_price` has no `language_id`.
3. `cms()->templates->add('consulting', from: 'course')` copies the fields in order.
4. Make `CreateField` fail on purpose (for example a reserved name): no table left behind.
5. Remove `price` from `course`: its table and rows remain.

**Done when:** field tables appear and disappear at runtime on both databases, and a failed field creation rolls back completely.

## Phase 3: Page writes

**Build**

- `Page` with dirty tracking per field and language, `SavePage` with value origins and the singleton check.
- `page_languages`, `page_closure`, `PathBuilder` and `RebuildPaths` with inactive paths, `MovePage`, `DeletePage`, `SwitchTemplate`, `PurgeOrphanedValues`.
- `cms:demo` now seeds a `/courses/` section with three courses, partly translated to Portuguese.

**After this phase:** a real page tree in two languages, built entirely through the API, with translated slugs and path history.

**Manual test**

1. Run `cms:demo`, then `select language_id, path, active from page_paths order by path` shows `courses/… for the default language and cursos/… for Portuguese (the pt prefix is added when routing)`.
2. Rename a course's Portuguese name and save: the old path stays with `active = false` next to the new one.
3. Move a course under another parent: every descendant gets new paths in both languages.
4. Switch a course to `consulting` with `dryRun: true` and read the list of fields that would be orphaned, then switch for real and switch back: the values return.
5. Try to create a second page on a singleton template: refused.

**Done when:** every write action has feature tests, including rollback and dry run, and old paths remain as inactive rows after renames and moves.

## Phase 4: Page reads and fallback

**Build**

- `PageLoader` with batch loading and eager loading (`with: ['related']`).
- `TranslatableValue` and `HtmlValue`, `PagesApi::get()`, `children()`, `$cms->language()`.
- Settings pages: `$cms->settings('name')`, cached per request and language; `cms:demo` adds a `_system/settings` page with a translated footer text.

**After this phase:** the full read side of the API. Partly translated pages fall back field by field, and global settings work in every language.

**Manual test** (Tinker)

1. `$p = cms()->pages->get('/courses/delivery-8/')`.
2. `cms()->language('pt', fn () => [(string) $p->title, $p->body->isFallback()])` returns the Portuguese title and `true` for the untranslated body.
3. `$p->getLanguageValue('pt', 'body')` returns `null` (strict, no fallback).
4. Save an explicit empty Portuguese summary: it now shows nothing instead of falling back.
5. `cms()->settings('settings')->footer_text` changes with the language.
6. Enable the query log and load `/courses/` with its children: the number of queries is `1 + fields`, not one per page.

**Done when:** the fallback matrix and query-count tests pass.

## Phase 5: Selector engine

**Build**

- Lexer, parser, AST and `SelectorCompiler` on the Query Builder; `find()`, `findOne()`, `count()`.
- Built-in keys and the MVP operators; `EXISTS` for shared fields, joins with `COALESCE` for translatable ones.
- A `cms:find "<selector>" --lang=pt` command that prints matching pages and the SQL, for manual checks and debugging.

**After this phase:** ProcessWire-style querying across languages, filtering and sorting on the values visitors actually see.

**Manual test**

1. `cms:find "template=course, price<300, sort=-title"` lists the courses in the expected order.
2. `cms:find "template=course, title%=Guia" --lang=pt` matches the Portuguese titles; courses without a Portuguese title match on their default-language title (effective value).
3. `cms:find "parent=/courses/, limit=2"` and `start=2` page through results.
4. Compare the printed SQL with expectations: `EXISTS` for `price`, `COALESCE` joins for `title`.

**Done when:** the parser and compiler fixture suites pass on both databases.

## Phase 6: Front end and app components

**Build**

- The three locale middlewares, the redirect for inactive paths, `PageController` (catch-all), and the default 404.
- Engine-neutral helpers (`HreflangBuilder`, `SeoResolver`) with Blade directives, `$page->url`, `localUrl()`, a language switcher partial.
- The component registry, `@cmsComponent`, and the optional Livewire integration with `UsesCmsPage`.
- `cms:demo` adds Blade templates for home, course list and course, plus an `EnrolForm` Livewire component in the workbench app storing enrolments in its own table.

**After this phase:** a working multilingual website. URLs are translated, old URLs redirect, the language is remembered, and an app Livewire form runs inside a CMS page.

**Manual test** (browser on `testbench serve`)

1. Open `/courses/delivery-8/` and `/pt/cursos/navegando-entrega-8/`: the Portuguese page shows the Portuguese title, the default-language body with `lang` set on it, and correct `hreflang` tags.
2. Rename the Portuguese slug in Tinker, then open the old URL: 301 to the new one.
3. Switch language with the switcher, then open a URL without prefix: you stay in the chosen language.
4. With the site running, add `es` in Tinker and give one course a Spanish name: `/es/…` resolves with no deploy or migration.
5. Submit the enrolment form on the Portuguese page with an error: validation messages stay in Portuguese, and a valid submission lands in the app's `enrolments` table with `lang = pt`.

**Done when:** route, redirect and middleware tests pass, and a Livewire test confirms the language survives `/livewire/update` requests.

## Phase 7: MCP server

**Build**

- Action descriptors (name, description, `JsonSchema` input) on every action, and the `mcp` package generating one tool per action.
- Read tools: `describe_schema`, `describe_field_types`, `get_template`, `find_pages`, `get_page`.
- The `ContentAssistant` prompt, the usage-guide resource, the language allowlist, and `actor_kind = mcp` in the activity log.

**After this phase:** any MCP client (Claude Desktop, Claude Code, an IDE) can inspect and build the site, previewing each change with a dry run.

**Manual test**

1. Open the laravel/mcp inspector against the workbench and list the tools: one per action plus the read tools.
2. Connect Claude Desktop or Claude Code to the server and ask: "Show me the schema of this site."
3. Ask it to create a `testimonial` template and three translated testimonials; check that it shows dry-run results before applying.
4. Restrict the allowlist to `default` and ask for a Portuguese edit: refused.
5. `select action, actor_kind from activity_events order by id desc` shows each step as `mcp`.

**Done when:** every generated tool has a test calling its action, and a blank-install scenario builds a translated page through MCP alone.

## Phase 8: AI agent (laravel/ai)

**Build**

- The `ai` package: `ActionTool` adapters from the same descriptors, `CmsAgent` (tools, conversation memory per user), plan mode with recorded changesets and exact replay.
- `TranslatePage` with an LLM `TranslationProvider` using structured output, saving `origin = machine`.
- Markdown import through league/commonmark, `$cms->ai()->ask()`, and `cms:ask` with `--file` and `--apply`.

**After this phase:** you can run the site in plain language from the terminal, review the plan, and apply it.

**Manual test** (needs an API key for one provider in `.env`)

1. `php artisan cms:ask "Create a new course with this content and translate it to PT" --file=storage/demo/course.md`: read the plan (a new course page, its fields, a Portuguese translation), approve it, then open both URLs in the browser.
2. In the database, the Portuguese values show `origin = machine` in `field_value_meta`.
3. `php artisan cms:ask "Create a template called consulting, copy the course fields, add x, y and z" --file=storage/demo/consulting-fields.md`: check field types, rules and conditions in the plan before approving.
4. Edit a Portuguese field by hand, then run `TranslatePage` again: your edit is kept and its origin is now `human`.
5. Ask for something that would delete data: the plan marks it as destructive and nothing runs without approval.

**Done when:** agent tests with `Ai::fake()` pass, including `assertToolsUsed`, and the two reference prompts produce the expected result against a real provider.

## Phase 9: Performance spike

**Build**

- `cms:demo --pages=50000 --languages=3` to generate a large dataset, and a `cms:bench` command timing typical selectors, sorts and page renders.

**After this phase:** measured numbers and a written decision (as a new ADR) on whether the effective-value queries are enough or the materialised search index moves into the next phase. **This closes the MVP.**

**Manual test**

1. Seed the large dataset on PostgreSQL and on SQLite.
2. Run `cms:bench` and record times for filtering and sorting on a translatable field in a language with a fallback chain.
3. Run `EXPLAIN ANALYZE` on the slowest queries printed by `cms:find`.

**Done when:** the numbers and the decision are recorded in the ADRs tab.

## Phase 10: Schema sync

**Build:** `cms:schema:export` and `cms:schema:apply` (YAML keyed by uuid, `--force` for destructive changes, conflict detection) and the option to lock schema editing in production. PHP template classes compiled by `cms:sync` were planned here and dropped (ADR-0133).

**After this phase:** schema changes made in production, by an editor or an AI agent, reach a developer's machine through git, and the other way round on deploy.

**Manual test:** create a field through `cms:ask` in a copy of the workbench acting as "production", export, commit, pull into a second checkout, apply, and confirm the field exists there. Apply twice: the second run changes nothing.

## Phase 11: Livewire admin

**Build:** the `admin` package following Lynx 1's PRD: page tree, page editor with per-field language tabs (double-click switches all), fallback previews and tab states, the Settings tab (names per language with URL preview, template, parent, status, redirects, backlinks, activity), component-field settings, screens to manage templates and fields (`documentation/plans/schema-admin.md`), text formatters (`documentation/plans/text-formatters.md`, ADR-0031), and roles and permissions (`documentation/plans/permissions.md`). Every screen calls the existing actions. The agent chat panel moved to phase 15.

**Progress:** built so far: the page tree (lazy branches, numbered pagers, ProcessWire-style inline actions, move mode with drag and drop and a keyboard fallback, theming through `--lynx-*` CSS variables, ADR-0024); the menu builder (`packages/menus`, ADR-0025), the first module on the admin's extension points; and the page editor up to step 8 of `documentation/plans/page-editor.md`: inputs per field type, rich text, page and component fields, optimistic locking, the Settings tab with old URLs, backlinks and activity, the unique status (ADR-0028), page references like ProcessWire (ADR-0029), and Review changes with Cmd/Ctrl+S and an unsaved changes warning.

**After this phase:** editors and site builders manage the demo site, its templates and its fields without code, each with only the rights their role grants.

**Manual test:** edit the Delivery course in Portuguese from the browser, leave the body untranslated and check the fallback preview, rename the Portuguese slug and confirm the redirect appears in the Settings tab, add a field to the course template from the admin, then sign in as an editor limited to `/courses/` and confirm the rest of the tree is read only.

## Phase 12: Lynx 1 modules

**Build:** port per-language scheduling, trash (`documentation/plans/trash.md`), content locking, drafts and revisions (Craft-style draft rows), export/import with backups and the Laravel Boost skills, each as a module on the public extension points. The headless REST API and webhooks moved to v2 (see the end of this plan).

**After this phase:** feature parity with Lynx 1 on the new core.

**Manual test:** lock a page in one session and try to save it from another; schedule a Portuguese version for tomorrow and confirm it is hidden until then.

## Phase 13: Translation modules

**Build:** the DeepL provider with `provider_codes`, glossaries per language pair, protected tags in rich text, outdated-translation detection from `source_hash`, `TranslateMissing` for whole languages, and review states in the admin.

**After this phase:** a new language can be added and machine-translated in one step, with human edits never overwritten.

**Manual test:** add `es`, run `TranslateMissing`, check that "Delivery" stayed untranslated through the glossary, change a default-language body, and confirm the Spanish version is flagged as outdated.

## Phase 14: Public release

**Build:** documentation in English and Portuguese (from Lynx 1's docs), a starter site, a security review, the 1.0.0 tag on Packagist, the private Composer repository for paid modules, and the first paid module.

**After this phase:** Lynx 1.0 is public, installable with `composer require`, and the open-core model is live.

## Phase 15: Agent chat panel

**Build:** a streaming chat panel for `CmsAgent` in the admin. The agent's plans show in the same review table as the page editor's Review changes (`ChangeReview` and `<x-lynx-admin::editor.review>`), with Approve and Discard; changes that are not field values (moves, status, schema) show as one summary line each. It comes last on purpose: every feature must work without it, through the screens, the API, MCP and `lynx:ask`.

**After this phase:** editors can ask for changes in plain language and approve them as diffs.

**Manual test:** ask the chat panel to add a field to the course template and fill it on two courses, review the plan, approve it, and find the changes in each page's activity.

## After 1.0 (v2)

Planned for the version after 1.0, moved out of phase 12 on 2026-10-08:

* **Headless REST API:** read and write pages over HTTP for decoupled front ends, with token authentication. Writes go through the actions, like MCP.
* **Webhooks:** signed HTTP calls to other systems when pages are saved, published, moved, trashed or deleted, queued with retries.

Both are planned in detail, with an Astro front end as the target, in `documentation/plans/headless.md`.

Moved here on 2026-11-20 from the road to 1.0:

* **A screen reader pass by a person.** Someone who uses VoiceOver or NVDA daily goes through signing in, the page tree (with move mode), the page editor with its language tabs and errors, and a form in the Forms module. The admin's accessibility so far is checked with axe and browser tests, not by listening (`documentation/reports/accessibility-review-2026-11.md`).

