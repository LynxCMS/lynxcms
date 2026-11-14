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
- Documentation is Markdown in `docs/`, built with VitePress and deployed from `main` to lynxcms.org/cms/docs, a Cloudflare Worker with static assets (`docs/wrangler.jsonc`, `.github/workflows/docs.yml`). Its pages and sidebar order live in `docs/.vitepress/config.mts`. A page with `draft: true` in its front matter shows in `npm --prefix docs run dev` and is left out of builds (the live site and PR previews) unless `DOCS_DRAFTS=1`. Write as [Writing](#writing) describes. Code the tutorial shows lives in `docs/snippets` and is run by `tests/Feature/Docs`, so change both together. The training site guide's code (`docs/snippets/training`) is also the starter app `LynxCMS/starter-training`, built by `.github/scripts/build-starter.sh` and pushed on every tag by `.github/workflows/starter.yml`.
- Wording people read in a package's views and Livewire components goes through `__()` with a namespaced key, in `lang/en` and `lang/pt` (ADR-0097). `tests/Unit/TranslationFilesTest.php` checks that translations match, and `tests/Unit/HardcodedWordingTest.php` refuses wording typed into a view. Action descriptions and validation errors from actions stay in English.
- Form controls in admin views follow the site's appearance and branding: use the admin's components (`<x-lynx-admin::ui.input>`, `ui.textarea`, `ui.select`, `ui.checkbox`, `ui.switch`) or a native control with BlatUI's class for it (`blat-input`, `blat-textarea`, `blat-select`, `blat-checkbox`, `blat-radio`), never a raw control with classes of its own. `tests/Unit/RawFormControlsTest.php` refuses one; hidden inputs, colour pickers and the control inside an input group (`data-slot="input-group-control"`) are fine. Views visitors see keep plain HTML.
- Tests use Pest and must pass on SQLite and PostgreSQL, in parallel. Tests that write files use `testingPath()` (one folder per process). Add a test for every action: happy path, validation failure, rollback, dry run.

## Writing

These rules cover every text people read: the docs, wording in the admin, action descriptions, error messages, commit messages and pull requests. Write plain declarative sentences that serve the reader. When a sentence is in doubt, ask whether it performs or informs, and keep only what informs.

### Style

- **Short sentences, one claim each.** Prefer two sentences to one joined by a comma, and drop any comma the sentence reads correctly without. Use bullets for lists with parallel stems and paragraphs for arguments.
- **Plain words.** No marketing words ("powerful", "seamless", "effortless", "simply", "just", "magic", "smart", "instantly"), no stock phrases ("just works", "out of the box") and no slogans ("Built for teams that ship").
- **Concrete subjects.** Attach the explanation to the thing it describes. Name things ("the MCP server") instead of describing how they relate to something else ("the tools it works with"). A claim should be specific enough that it could not describe another product.
- **Facts stated once.** Let interesting facts speak for themselves, with no manufactured rhythm, drama or contrast.
- **A little dry humor is welcome** where it costs no clarity: a few lines per page at most, inside sentences that also carry a fact, never in code, steps or definitions. Many readers don't have English as a first language, so the joke uses plain words too.
- **American spelling:** color, gray, catalog, behavior, organize, license, canceled. Code keeps the spelling it has (`CourseCatalogue`, a stored `cancelled` value).
- **No dashes as punctuation.** Use a period, a comma, a colon or parentheses.

### Banned patterns

- "X, not Y" contrasts and dramatic reversals ("It is not X. It is Y.").
- Staged openers and closers ("Here's the thing", "The catch is", "That's the point"), and a punchy line that ends a section. Stop when the information stops.
- Narration about the text: callbacks to an earlier question, and telling readers why a sentence matters.
- Coined metaphors and epigrams, above all for technical ideas. Use the domain's own terms.
- Fragments for drama. Write complete sentences.
- Defensive or relationship framing toward the reader. Describe the operation.
- A count added as a trailing aside. Give it its own short sentence.

### Headings and labels

- A heading names a thing ("Fallback", "Request flow") or a task ("Listing the team by role"). It never starts with How, What, Why, Where or When, and is never a question.
- Write headings like email subject lines: no category label above them, no numbering unless the order matters, and no rhythmic, metaphorical or vague titles ("Hard to describe. Easy to recognize.", "A closer look", "Why it matters").
- Buttons and other actions use a clear verb: "Save changes", "Publish draft".

### Admin wording

Admin wording says what a control does in the fewest plain words. It never sells, charms or chats.

- A field's label is the plain noun or verb for its value: "Show" beside a number instead of "How many", "Visibility" instead of "Who can see this".
- No exclamation marks and no cheerful asides in labels, help text, toasts or empty states.
- Help text says what happens and where, in one short sentence: "Lists your 6 most recent pages. Updates when you publish."

### Docs

The docs aim to be as easy to follow as Laravel's. The reader is a PHP developer with, maybe, some basic Laravel experience, who may never have used another CMS or built a site in several languages.

- **Lead with what the reader can do**, then show the code, then explain how it works, only as far as the reader needs it. "You can add a language to a live site in one step" comes before how languages are stored.
- **Show an example before naming an idea**, and define each Lynx term where a page first uses it (template, field, selector, fallback, action, dry run, site text) or link to the page that defines it.
- **Explain Laravel's own terms in a few words** the first time a page relies on them: a seeder, a service provider, a Blade component, a migration, Artisan.
- **No comparisons with other CMSes** outside `getting-started/compared.md` (crediting ProcessWire, where many of Lynx's ideas come from, is fine), and no contrasts with an alternative the reader may not know ("a row, not a column"). Say what Lynx does.
- **One idea per paragraph.**
- **Guides** say what the reader is building first, then each chapter adds one part and ends with what the reader and the site's owner now have. Code in a guide is real: a tested snippet in `docs/snippets`, or a shortened excerpt of a starter app, saying so.
- Reference pages (Configuration, Artisan, Field Types) may stay terse: readers arrive there knowing what they look for.

## Checks while Lynx has few users

Keep the loop short; CI is the safety net.

- Before pushing code: Pint, PHPStan and the tests of what changed (`vendor/bin/pest tests/Feature/...`). The full suite and Rector run in CI. Docs, wording and CI changes need only Pint where PHP changed.
- Pull requests run SQLite tests and the quality job; PostgreSQL and the table prefix run on `main` after the merge. Fix a failure there right away.
- Releases come in batches, when something worth shipping has gathered, not after every pull request. A release is a commit on `main` that turns "Unreleased" into the version heading, then the tag; no pull request of its own.

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
