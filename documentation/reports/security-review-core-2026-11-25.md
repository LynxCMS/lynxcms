# Security review of the core package, 2026-11-25

Reviewed on 2026-11-25, on `main` at `8a1439d8`. The review was read-only: no code changed, no test ran, and no finding was exercised against a running site. Every finding below was traced in the code, and the file and line are given so each can be checked.

## Scope

Covered: `packages/core` (`src`, `config`, `database`, `resources`), with SQL injection as the main question. Three passes ran side by side:

1. Every place that builds SQL at runtime: the selector parser and compiler (`src/Query`), the search engines, runtime DDL for field tables, trigram indexes, migrations, and every `Sql::trusted()`, `DB::raw()` and `*Raw()` call.
2. Authorization: `src/Permissions`, the action runner, every action's permission and scope checks, the agent toolkit and the visitor read path.
3. HTTP and output: the page controller and middleware, HTML cleaning and formatters, view resolution, YAML and archive handling, activity logging.

Not covered: the other packages (admin, MCP tool classes, media, forms, cache, page passwords), the admin's Livewire components and JavaScript, the remaining field types (choice, date, page table), `ExportCommand` and `BackupCommand` output paths, dependency advisories, and testing against a running server. The earlier report (`security-review-2026-11.md`) covers sign-in, two-factor authentication, media and the page cache.

## Summary

No SQL injection was found. Values reach the database through bindings in every path read, and every identifier that appears in SQL comes from a registry or a constant, then passes through the grammar's quoting.

The most serious finding is outside SQL: `pages.duplicate` checks no scope, so a role limited to one branch or template can copy any page anywhere.

| Id | Severity | Finding | Where |
|---|---|---|---|
| H1 | High | `pages.duplicate` skips every scope check | `Actions/Pages/DuplicatePage.php:76` |
| M1 | Medium | A whole selector taken from a visitor lists unpublished pages and reads any field by guessing | `Query/PageFinder.php:41`, `docs/basics/selectors.md:236` |
| M2 | Medium | Changing a field's format to `html` leaves stored values uncleaned | `Actions/Fields/UpdateField.php:78`, `Fields/FormatterChain.php:47` |
| M3 | Medium | Selectors ignore a field's view access | `Agents/AgentToolkit.php:188` |
| L1 | Low | The action runner stays "nested" when a transaction fails to start, which switches authorization off | `Actions/Runner/ActionRunner.php:118` |
| L2 | Low | A label can close the Blade comment in a generated view | `Templates/Views/ViewStub.php:28` |
| L3 | Low | A language's `locale` is not validated | `Actions/Languages/CreateLanguage.php:64` |
| L4 | Low | `schema.manage` can remove a field's `access` and `locked` settings | `Actions/Fields/UpdateField.php:60` |
| L5 | Low | Name patterns ending in `$` accept a trailing newline | `Actions/Fields/CreateField.php:72` and others |
| L6 | Low | Validation runs before authorization and answers questions about the schema | `Actions/Runner/ActionRunner.php:106` |
| L7 | Low | `linked_from` in page history lists pages outside the actor's scope | `Agents/PageHistory.php:51` |
| L8 | Low | Redirects and hreflang links are built from the request's Host header | `Http/Controllers/PageController.php:209` |

## SQL injection

### Result

None found. The rule in `AGENTS.md` (identifiers through the grammar, whitelisted operators, integer ids, values through bindings) holds in every call site read.

### What was traced

**Selector parser** (`Query/SelectorParser.php`). A clause must match one pattern: a field name of `[a-z_][a-z0-9_.]*` (several joined by `|`), one of eleven fixed operators, then the value. A NUL byte is refused. Nothing else reaches the compiler.

**Field names** (`Query/SelectorCompiler.php`). A name in a clause or in `sort=` is one of a fixed list (`id`, `template`, `parent`, `name`, `created` and so on) or is looked up in `FieldRegistry`. An unknown name throws. The table name is `lynx_f_` plus the registry's name, never the visitor's text, and the query builder quotes it.

**Operators** (`Query/ClauseComparison.php:39`). `{$sql} {$clause->operator} ?` puts the operator into SQL. It can only be `<`, `>`, `<=` or `>=`, because of the `match` arm it sits in and the parser's pattern. Every `new Clause(...)` in the code base is in `src/Query` and passes on an operator that came from the parser.

**Values**. Always bound. `LIKE` values escape `\`, `%` and `_` and use `ESCAPE '\'`. `limit` and `start` are cast to integers. Dates pass through Carbon or `strtotime` and are bound. Page references are digits or a path resolved through a bound query (`Pages/PathResolver.php:63`).

**JSON keys** (`Query/JsonSql.php`). Subfield and property names appear inside SQL string literals. Each passes `assertKey()` (`[a-z][a-z0-9_]*`) first. Language codes in a JSON path pass `[A-Za-z0-9_-]+`. Columns pass through `wrap()`.

**Raw fragments** (`ValueJoins`, `CountClause`, `ItemPropertyCompiler`, `SubfieldCompiler`). Each is built from wrapped identifiers, generated aliases (`f1`, `l0`), constants and integer language ids from the registry.

**Search** (`Search/Engines/*`, `Search/SearchText.php`). Search words are split on anything that is not a letter, digit or combining mark, so quotes, `%`, `_`, `:`, `&`, `|` and parentheses never reach an engine. The FTS5 match string and the PostgreSQL `tsquery` are bound all the same.

**DDL** (`Fields/FieldSchemaManager.php`, `Indexes/TrigramIndexes.php`). Table and index names come from the field's validated name, through the schema builder or `wrap()` and `wrapTable()`.

**Scopes and state** (`PageScope`, `PageStateMatch`, `StatusFilter`, `UrlFilter`, `Backlinks`). Query builder only, with integer ids and fixed status names.

**Selectors the core builds itself**. `SortChildren` validates `by` with `Rule::in`. `PageLookup::named()` only runs when the text equals its own slug. Template names in `ScopeRules`, `MakeViewCommand` and `TemplatesCommand` come from the registry.

**Array conditions** (`->where([...])` in `PageStringEdits`, `SaveStrings`, `SchedulePage`, `CleanHtmlValues`). The keys are fixed column names; only the values vary.

### Notes that are not vulnerabilities

- `database/migrations/2026_10_06_000000_create_lynx_tables.php:117` writes the connection prefix into two index names without quoting. The prefix is the app's own configuration, so this is tidiness only.
- `Clause` accepts any string as its operator. The parser is the only guard. Checking the operator in the constructor would keep the guarantee when someone builds a clause elsewhere.
- `tests/Feature/Query/SelectorSecurityTest.php` already covers hostile values, wildcards, identifiers and NUL bytes. It has no case for OR groups (`(a=1), (b=2)`) or for nested properties (`blocks.gallery.tags=`).

## Findings

### H1. `pages.duplicate` skips every scope check

`DuplicatePage::permission()` is `pages.create`. The runner checks an outer action's permission with `Authorizer::authorizeAction()` (`Permissions/Authorizer.php:113`), which asks only whether the actor holds it somewhere. The action is then expected to check the page with `$context->authorize()`.

`DuplicatePage::handle()` (`Actions/Pages/DuplicatePage.php:76`) never does. It resolves the source page and the parent, then creates the copies with `$context->run(SavePage::class, ...)`. Those runs are nested, and `ActionContext::authorize()` returns at once for a nested action (`Actions/Runner/ActionContext.php:53`), so `SavePage`'s own checks do not run either. `assertCopyable()` and `assertCanGoUnder()` look at the trash, drafts and the branch, never at permissions.

**Who:** any actor whose role holds `pages.create` in any scope, through the admin, MCP, the AI agent or the PHP API.

**Effect:** the actor copies any page, or a whole branch with `children: true`, under any parent, whatever branches and templates their grants name. Copying a page they may not view into a branch they may view also shows them its content.

**Fix:** in `handle()`, authorize the source (`pages.view` on the page, and on every page of the branch when `children` is true) and the destination (`pages.create` with the parent and each copied template). `tests/Feature/Pages/DuplicatePageTest.php` has no permission case, so add one for a role limited to a branch and one for a role limited to a template.

The class comment says every page is created with `pages.save`, "which checks the user's permissions". That is not true for nested runs. Other actions that create or change pages through nested runs deserve the same look: `BuildDraft` and `CreateTemplate` do it too, though both need `schema.manage`.

### M1. A whole selector from a visitor

`docs/basics/selectors.md:236` shows `lynx()->pages->find($request->string('q'), maxLimit: 50, defaultLimit: 20)` for an app that takes a whole selector from a request, and says to give it a cap. A cap is not enough:

- `include=all`, `include=unpublished`, `status=unpublished` and `has_draft=1` are part of the selector language, so the visitor lists unpublished, hidden and system pages. The next paragraph of the same page warns about exactly this for the other case (a visitor's words inside the app's selector).
- Any field can be filtered and sorted, including ones no template shows. `notes^=a`, `notes^=b` and so on read a value one character at a time from whether the page is found.
- Nothing limits the number of clauses, OR groups or `~=` words, and `start=` takes any offset, so one request can ask for an expensive query.

SQL stays safe here. What leaks is content.

**Fix:** give `find()` a mode for selectors that come from visitors. It refuses `include`, `status`, `has_draft`, `has_schedule` and `has_outdated`, takes an allowlist of fields, and caps the number of clauses. Until then, change the docs so the example validates or strips those terms. The parked plan for bindings in selectors solves the other case (words inside the app's selector), not this one.

### M2. Format changed to `html` without cleaning

Rich text is cleaned when it is saved (`TextareaType::normalize()`), never when it is read. `FormatterChain::base()` (`Fields/FormatterChain.php:47`) prints a field whose format is `html` as stored. `UpdateField::handle()` (`Actions/Fields/UpdateField.php:78`) lets `settings.format` change and cleans nothing. `CleanHtmlValues` exists for this but only `lynx:clean-html` calls it.

**Who:** an editor saves `<script>` in a plain or Markdown field, where it is harmless because it is escaped or stripped on output. Later someone with `schema.manage` turns the field into rich text. The stored value now runs for every visitor and in the admin. The second person needs no bad intent: switching a field to rich text is routine.

**Fix:** when `format` changes to `html`, run `CleanHtmlValues` for the field inside `UpdateField` (`$context->run()`), or clean on read in the `html` branch.

### M3. Selectors ignore a field's view access

`FieldAccess` hides a managed field from roles its `access` setting does not list. `AgentToolkit` applies it when describing the schema and returning a page (`Agents/AgentToolkit.php:156`, `:237`). `findPages()` (`:177`) hands the selector to `PageFinder` unchanged, and nothing in `src/Query` looks at `FieldAccess`.

**Who:** an MCP or AI actor (or an editor on any screen that takes a selector) with `pages.view`, whose role may not view the field.

**Effect:** `salary>50000`, `secret^=ab` or `sort=secret` answer questions about the hidden value through `total` and the order of results. `search*=` does the same for a hidden field that is marked searchable.

**Fix:** before compiling a selector for an actor, refuse clauses and sorts on fields the actor may not view. The compiler already resolves each field in one place (`whereField()`, `orderBy()`, `SubfieldCompiler`, `CountClause`), so a callback given to `PageFinder::find()` can do it.

### L1. The runner stays nested when a transaction fails to start

`ActionRunner::attempt()` (`Actions/Runner/ActionRunner.php:118`) runs `$this->depth++` and `beginTransaction()` before the `try`. When `beginTransaction()` throws, `depth` is never lowered. The runner is a singleton, so in a long-lived process (Octane, FrankenPHP, a queue worker) every later action counts as nested: the runner skips `authorizeAction()` and `ActionContext::authorize()` returns early. Authorization is off until the process restarts.

Nobody can cause this at will. It needs a database error at that moment and a process that outlives the request. It fails open, which is why it is listed.

**Fix:** move `beginTransaction()` inside the `try`, and lower `depth` in a `finally`.

### L2. A label can close a Blade comment in a generated view

`ViewStub` writes `'{{-- '.$template->labels()->in().' ('...` (`Templates/Views/ViewStub.php:28`, also `:45` and `:55`), and `BlockViewStub.php:27` does the same with a block's label. Labels are free text. A label holding `--}} @php ... @endphp {{--` becomes PHP in a `.blade.php` file the next time a developer runs `lynx:make:view` or `lynx:draft`.

**Who:** someone with `schema.manage`, and a developer who then generates the view. `BuildDraft` over MCP never writes files, so an agent alone cannot do it.

**Fix:** remove `--}}`, `{{`, `@` and newlines from labels before they go into a stub.

### L3. `locale` is not validated

`CreateLanguage` and `UpdateLanguage` validate `locale` as `['nullable', 'string', 'max:32']`. `LanguageContext` passes it to `App::setLocale()` on every request, and Laravel's translation loader builds a file path from it. A value such as `../../x` makes the loader look outside `lang/`. Running code this way also needs a PHP file at the matching path, so it is unlikely, but `code` and `prefix` are restricted and `locale` should be too. The value also goes into `lang` attributes (escaped).

**Fix:** a pattern such as `/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*\z/`.

### L4. `schema.manage` can remove `access` and `locked`

`UpdateField` accepts `settings.access` and `settings.locked`, and a `null` removes a setting. A field with an empty access list is described as superusers only, and a locked field as site code only. Any role with `schema.manage` can lift both and then read or write the field.

**Fix:** require a superuser to change these two settings, or state in the docs that `schema.manage` amounts to full access to content.

### L5. Patterns ending in `$` accept a trailing newline

In PHP, `$` also matches before a final newline. `php -r 'var_dump(preg_match("/^[a-z][a-z0-9_]*$/", "abc\n"));'` prints `1`. The affected rules: field names (`CreateField.php:72`), template names (`CreateTemplate.php:60`, `:71`, `:73`, `UpdateTemplate.php:73`, `:75`), language codes and prefixes (`CreateLanguage.php:61`, `:66`, `UpdateLanguage.php:59`, `:64`), role names (`SaveRole.php:51`), matrix type slugs (`MatrixType.php:46`), `CopySection.php:66`, `JsonSql::assertKey()` and the layout keys in `FieldListInput` and `LayoutRules`.

HTTP requests are trimmed by Laravel, but action input from MCP, the AI agent, YAML and the PHP API is not. A field named `"abc\n"` would get the table `lynx_f_abc\n`. Identifiers stay quoted, so this is not SQL injection. It breaks the assumption that names are plain identifiers, which view paths, file names and URL prefixes rely on.

**Fix:** end each pattern with `\z`, or add the `D` modifier.

### L6. Validation before authorization

`ActionRunner::attempt()` validates input (`:106`) before it authorizes (`:124`). An actor who may not run an action still gets validation messages, and rules such as `Rule::unique` and `Rule::exists` say whether a template, field or role name exists. Nothing is written.

**Fix:** authorize outer actions before validating. `$action::permission()` is static, so the check needs no input.

### L7. `linked_from` outside the actor's scope

`PageHistory` (`Agents/PageHistory.php:51`) adds `lynx()->pages->backlinks($page)` to what `get_page_history` returns. The list is not filtered by the actor's `pages.view` scope, so id, path, title and template of pages in other branches show, unpublished ones included. The `referenced_by` ids in the change summaries of `TrashPage` and `DeletePage` are unfiltered too.

**Fix:** filter with `Authorizer::scopeOf($actor, 'pages.view')` and `PageScope::covering()`.

### L8. URLs built from the Host header

`PageController.php:209`, `RedirectDefaultLocale.php:40` and `HreflangBuilder.php:52` build absolute URLs with `url('/')`, which follows the request's Host header unless the app configures trusted hosts. A forged header changes where a 301 points and what hreflang links say. It matters when a shared cache or the page cache stores the response by path alone. `ExternalLinks` already uses `config('app.url')`.

**Fix:** build these from `config('app.url')`, or say in the deployment docs that `TrustHosts` is required.

## Observations

- **Existence of pages.** `AgentToolkit::getPage()` returns null for a missing page and throws for one the actor may not view, which tells the two apart.
- **Last superuser role.** `SaveRole` can clear `is_superuser` on the only superuser role. `DeleteRole` and `AssignRoles` check for this; `SaveRole` does not. This locks people out but gives nobody more rights.
- **Visibility rules and direct URLs.** `PageController` never calls `PageVisibility::allows()`. Rules narrow lists only, and a module must add a gate to refuse the URL. `docs/deeper/visibility.md` says so, but the comment on `VisibilityRule` suggests single pages are covered.
- **`RememberLocale`** writes the session on every page view, so every visitor and bot gets a session and a cookie. It is opt-in.
- **`PackageArchive::extractTo()`** has no limit on unpacked size. The check against paths leaving the folder is sound. Import is for superusers.
- **`$guarded = []`** on the system models is safe today because every `fill()` uses a fixed key map. It stops being safe the day one takes input directly.

## Checked and found sound

- **Authorization defaults.** An action without a permission is for superusers only. A guest and a user without a role have no rights. `Authorizer::covers()`, `PageScope` and `authorizePages()` agree with each other.
- **Per-action checks.** `MovePage` (source and destination), `RestorePage`, `SwitchTemplate` (both templates), `TrashPage`, `DeletePage`, `EmptyTrash` (whole branch), `SortChildren` (each child), `SetPageStatus`, `SchedulePage`, the draft actions, `TranslatePage`, `DeleteRedirect` (filtered by page) and `SavePage` all check the page they act on.
- **Field protections on write.** `ValueWriter` enforces `locked` and `FieldAccess::canEdit()` with the real actor, in nested runs too.
- **Role management.** Superusers only, and not exposed as tools.
- **HTML.** Symfony's HtmlSanitizer with an element allowlist runs on every save path found: the editor, imports, revision restore, translation, draft publishing and items in repeaters and matrices. Markdown strips HTML and unsafe links. `UrlType` refuses `javascript:`, `//host` and backslashes. Site texts are escaped.
- **Visitor path.** Unpublished and trashed pages return 404. Previews need a signed-in user and the `lynx.pages.view` or `lynx.pages.edit` gate, and send `no-store`. Redirects from old paths run only after the published check.
- **View resolution.** View names come from validated template names, field names and block slugs. `ComponentRenderer` only builds classes the developer registered.
- **Files and processes.** YAML is parsed without object support. No `unserialize`, `eval` or shell call exists in the core. `Process` takes argument arrays.
- **Activity log.** Field names and summaries only, no values, passwords or tokens.

## Suggested order

1. H1, with tests for scoped roles. It is a small change to one action.
2. M2 and L1. Both are a few lines.
3. M3 and M1 together, since both need the compiler to say which fields and terms a selector uses.
4. L5 as one sweep over the patterns, then L2, L3, L6, L7.
5. Decide L4 and L8 (code or docs).

## Resolution

Fixed on 2026-11-25 on the branch `fix/core-security-review`, one commit per finding, each with a test that fails without the fix.

| Id | Fix |
|---|---|
| H1 | `DuplicatePage` authorizes `pages.view` on every page it copies and `pages.create` under the new parent for each template among them. |
| M1 | `SelectorPolicy::forVisitors()` (ADR-0153), passed as `policy:` to `lynx()->pages->find()`: only the fields named, no `include`, `join`, `status` or `has_` terms, and caps on clauses, values and `start=`. The selectors page shows it. |
| M2 | `fields.update` runs `fields.clean_html` for a field whose format becomes `html`. |
| M3 | `SelectorPolicy::forActor()` refuses fields the actor may not view, and `search` while such a field is indexed. Agents and the Finder use it. |
| L1 | The transaction starts inside the `try`, and the rollback only runs when it began, so `depth` always goes back down. |
| L2 | `BladeComment::text()` drops braces, `@` and line breaks from labels before they go into a generated view. |
| L3 | `locale` must match `Language::LOCALE_PATTERN`. |
| L4 | Only superusers change a field's `access` or `locked`, or lock and unlock a field on a template's row. The field form leaves the Access tab and the lock out for other roles, and the template form shows its Locked boxes read only. |
| L5 | Every end-anchored pattern that checks a name, key, code or stored value has the `D` modifier. |
| L6 | The runner authorizes an outer action before it validates the input, and empties its caches when it refuses one. |
| L7 | `linked_from` holds only pages the actor may view. The `referenced_by` ids in change summaries stay complete: they are the record of the change in the activity log. |
| L8 | Redirects and hreflang links use `config('app.url')`. The deployment page explains `APP_URL` and Laravel's trusted hosts. |

Not changed: the notes that are not vulnerabilities, and the observations.
