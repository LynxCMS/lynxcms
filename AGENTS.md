# AGENTS.md

Guidance for AI coding agents and contributors working in this repository.

## What this is

Lynx is a multilingual CMS packaged for Laravel 13 / PHP 8.5. This is a monorepo: one Composer root (`lynxcms/monorepo`) with packages that `.github/workflows/split.yml` copies into read-only repositories (`LynxCMS/core`, `LynxCMS/admin`...) on every push to `main` and every tag. Changes are made here only; pull requests to the split repositories are closed automatically:

| Package | Namespace | Role |
| --- | --- | --- |
| `packages/contracts` | `LynxCms\Contracts` | Interfaces and DTOs modules depend on. No implementation. |
| `packages/core` | `LynxCms\Core` | Languages, fields, templates, pages, selectors, actions, routing, Blade helpers. |
| `packages/mcp` | `LynxCms\Mcp` | MCP server generated from the action registry (laravel/mcp). |
| `packages/ai` | `LynxCms\Ai` | Built-in agent and translation provider (laravel/ai). |
| `packages/admin` | `LynxCms\Admin` | Livewire admin: page tree, page editor, templates and fields screens, roles and users. |
| `packages/menus` | `LynxCms\Menus` | Menu builder: menus as pages, the menu editor and `<x-lynx-menu>`. |

The design and the decisions behind it live in the plan document and its ADRs. Do not change a decision without a new ADR.

## Core rules

1. **Every write is an action.** Actions live in `packages/core/src/Actions/*`, extend `LynxCms\Core\Actions\Action`, are `final`, and have one `handle(Input $input, ActionContext $context)` method plus static `name()`, `description()`, `rules()`, `schema()`. Register new actions in `LynxServiceProvider` (or a module's provider) through `ActionRegistry`; MCP and AI tools appear automatically.
2. **Run actions through `ActionRunner`**, never by calling `handle()` directly. Inside an action, call other actions with `$context->run()`.
3. **Record changes** with `$context->changes->created/updated/deleted/schema(...)`. They are the dry-run output, the activity log and the AI plan. Mark data removal as destructive.
4. **Languages are rows.** Never add per-language columns. Translatable field tables are keyed by `(page_id, language_id)`.
5. **Fallback happens at read time** (`TranslatableValue`, `ValueJoins::effectiveValue`). Never copy values between languages to fake a fallback.
6. **Field data is never deleted implicitly.** Removing a field from a template or switching templates keeps values as orphans; only `PurgeOrphanedValues` deletes them.
7. **Paths are never deleted on rename or move.** The previous row becomes inactive and redirects.
8. **Eloquent only for system tables** (`Language`, `Field`, `Template`, `ActivityEvent`, `Setting`, `Role`, `RoleGrant`, `RoleAssignment`). Pages are domain objects hydrated by `PageLoader`.
9. **The core must not depend on** `laravel/mcp`, `laravel/ai`, Livewire (except the optional `Livewire\UsesLynxPage` trait) or the admin. Architecture tests enforce this.
10. **Public API is explicit.** Mark classes `@api` or `@internal`. Paid modules may only use `@api` code, and they live in their own private repositories, never in this monorepo.

## Conventions

- `declare(strict_types=1);` everywhere; Pint (`composer format`), Rector (`composer refactor`) and PHPStan at level `max` (`composer analyse`) must pass, with no baseline; CI runs all three in check mode (`composer check`). Run Rector before Pint.
- Read loosely typed data (database rows, validated input, config, YAML, JSON settings) through `LynxCms\Core\Support\Value` instead of casting `mixed`. Check action results with `ActionResult::valueAs()` / `valueOrDryRun()`.
- Raw SQL built at runtime goes through `LynxCms\Core\Query\Sql::trusted()` / `Sql::raw()`, and only from grammar-wrapped identifiers, whitelisted operators and integer ids. Values always go through bindings.
- Tables are prefixed `lynx_`; field tables are `lynx_f_{name}` (indexes `{table}_pk`, `_lang`, `_data`), so no core table may start with `lynx_f_`; field names are capped so these fit PostgreSQL's 63-character identifiers (`Tables::maxFieldNameLength()`). Lynx must also work under the host app's connection prefix (`composer test:prefix`): in raw SQL, wrap table names with the grammar (`wrapTable`, `wrap`) instead of writing them out.
- Artisan commands are `lynx:*`; the helper is `lynx()`; the facade is `Lynx`.
- Documentation is Markdown in `docs/`, built with VitePress and deployed from `main` to lynxcms.org/cms/docs, a Cloudflare Worker with static assets (`docs/wrangler.jsonc`, `.github/workflows/docs.yml`). Its pages and sidebar order live in `docs/.vitepress/config.mts`. A page with `draft: true` in its front matter shows in `npm --prefix docs run dev` and is left out of builds (the live site and PR previews) unless `DOCS_DRAFTS=1`. Write for a developer who knows basic Laravel. Code the tutorial shows lives in `docs/snippets` and is run by `tests/Feature/Docs`, so change both together. The training site guide's code (`docs/snippets/training`) is also the starter app `LynxCMS/starter-training`, built by `.github/scripts/build-starter.sh` and pushed on every tag by `.github/workflows/starter.yml`.
- Tests use Pest and must pass on SQLite and PostgreSQL, in parallel. Tests that write files use `testingPath()` (one folder per process). Add a test for every action: happy path, validation failure, rollback, dry run.

## Commands

```bash
composer test                          # SQLite in memory, in parallel (one process per core)
composer test:pgsql                    # PostgreSQL in parallel: lynx_test_test_1, _2… made per process
composer test:prefix                   # SQLite with DB_PREFIX=app_
composer test:all                      # the three above, as CI runs them
composer test:serial                   # one process, for debugging order-dependent tests
composer analyse                       # PHPStan
composer format                        # Pint
composer refactor                      # Rector (then composer format)
composer check                         # Pint, Rector and PHPStan in check mode, as CI runs them
npm --prefix docs run dev              # live preview of the documentation site (npm --prefix docs ci once); run build to check links, as CI does
composer assets                        # build the admin's CSS and JS into packages/admin/dist (after npm --prefix packages/admin ci once); built, never committed (ADR-0071)
.github/scripts/build-starter.sh 0.1.8 ../atlas   # build and test the training starter app
composer build && composer serve       # workbench demo site
php vendor/bin/testbench lynx:find "template=course, sort=title" --lang=pt --sql
php vendor/bin/testbench lynx:demo --pages=50000 --languages=1   # performance data
php vendor/bin/testbench lynx:bench --lang=pt
```
