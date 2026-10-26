---
title: Release Notes
description: What changed in each Lynx release.
order: 1
---

# Release Notes

Every `lynxcms/*` package shares one version number, and only classes marked `@api` are public API. The [upgrade guide](/prologue/upgrade) lists what to do between releases.

## Unreleased

- **Friendlier commands in a terminal.** `lynx:superuser` checks the email and password as you type them. `lynx:import`, `lynx:export`, `lynx:find` and `lynx:ask` ask for a missing argument, `lynx:page-cache` without flags lets you pick what to do, and `lynx:import --prune` and `lynx:schema:apply --force` show what they would remove and ask first. Long commands (`lynx:backup`, `lynx:export`, `lynx:import`, `lynx:static`, `lynx:demo`, `lynx:bench`, cache and sitemap warming, the agent's planning) show a spinner or a progress bar. Commands run from code, a pipe, a scheduler log or with `--no-interaction` never ask and print plain lines, as before.

- A translatable text reads as `null` when no language in the visitor's fallback chain has a value, even if another language holds one, so `@if ($page->job_title)` and `$page->job_title ?: $default` work on pages translated only in part. Before, such a field read as an empty object, which PHP treats as true. See the [upgrade guide](/prologue/upgrade) if your code reads other languages through it.

## 0.1.21

Released October 26, 2026. Nothing to run after updating; templates are scanned for texts again once.

- **Values in editable texts.** `@string` takes `replace:`, values for `:name` placeholders, so a text such as ":room is free until :time." is one text editors can reword and translate. The Texts tab lists each text's placeholders. See [Values in texts](/basics/views#values-in-texts).
- Templates that place a Livewire component (`<livewire:name>` or `@livewire('name')`) now declare the strings of its view, `livewire/name.blade.php`, so they show in the page's Texts tab. See [Texts in Livewire components](/basics/views#texts-in-livewire-components).
- `lynx:strings:scan` lists only templates that have a view. Templates without one (settings pages, menus, screens) declare no strings; they are counted below the table instead of listed as empty rows.
- **A new guide: [Build a Meeting Rooms App](/guides/meeting-rooms/).** Eight chapters build an application on Lynx: a law firm's meeting rooms, booked from the admin, a staff page, door and lobby screens and AI assistants, all through the same actions, with [Laravel Zap](https://www.laravel-zap.com/) for the calendars. The finished app is a starter you can clone, updated to each release: [LynxCMS/starter-meeting-rooms](https://github.com/LynxCMS/starter-meeting-rooms).

## 0.1.20

Released October 25, 2026. Run `php artisan lynx:install` after updating to get the page not found (see the [upgrade guide](/prologue/upgrade)).

- **The page not found is a page in the tree.** `lynx:install` creates `/not-found/`, a system page with a `not-found` template, which Lynx shows with status 404 and `noindex` for any front-end address that matches no page, in the visitor's language. Editors write it like any page. Superusers pick another page, or none, under **Settings > General** (`pages.set_not_found`). See [The page not found](/basics/views#the-page-not-found).
- **The site's name, logo and colours in the admin.** Superusers set them under **Settings > General**, with a preview; developers set defaults in `config/lynx-admin.php` (`LYNX_ADMIN_NAME`, `LYNX_ADMIN_LOGO`, `LYNX_ADMIN_BACKGROUND`, `LYNX_ADMIN_BRAND_ACCENT`). They show on the sign-in screens, in the sidebar and in the browser's tab titles. See [The site's name, logo and colours](/admin/#the-site-s-name-logo-and-colours).
- The admin's sidebar keeps its state, open or collapsed, between pages and visits, and no longer flashes open on each page.
- **French for visitors.** The forms and page password modules ship French strings next to English and Portuguese, so a `fr` language (or a regional locale such as `fr-CA`) shows them without a published copy.
- A form's default success and error messages (when it doesn't define `successMessage()` or `errorMessage()`) come from `lynx-forms::forms.sent` and `.failed`, in the visitor's language. They were English for everyone.

## 0.1.19

Released October 24, 2026.

- **PHPStan types fields through an extension.** `lynx:phpstan` (formerly `lynx:phpstan-stub`) writes your fields' types, and `vendor/lynxcms/core/phpstan/extension.neon` reads them, for pages and for repeater and matrix items. The stub it replaces failed PHPStan 2's stub checks, which can't see vendor classes, and left `$page->name` read only. See [Static analysis](/deeper/custom-field-types#static-analysis).
- `lynx:install` no longer asks to create a superuser when code runs it (`Artisan::call()` in a seeder or a test), where nobody can answer: it waited forever in tests, and elsewhere started `lynx:superuser` with no answers. It asks only when typed in a terminal.

## 0.1.18

Released October 24, 2026. Security hardening, and media from production for developers' machines. Run `php artisan lynx:migrate` after updating.

- **Media from production, on demand.** On a developer's machine, `LYNX_MEDIA_REMOTE` names production's media folder, and missing public files download from it when a page or an image size needs them. `lynx:export --without-files` makes a light package for this, and `lynx:media:pull` queues the downloads ahead. See [Working on a Live Site](/deeper/live-site#media-from-production).
- **The agent no longer holds the database while you read its plan.** Its changes are rolled back before the plan is shown, and approving replays them in a short transaction of their own. A run also has two minutes (`LYNX_AI_PLAN_SECONDS`); past that it stops and keeps nothing, so a slow model can't keep other writers waiting on SQLite.
- **Invitation links and password reset links are kept apart.** Invitation tokens have their own table, so a reset link, which lasts an hour, can't be used as a days-long invitation, and an invitation can't reset a password. Run `php artisan lynx:migrate`; links already sent keep working.
- **Form uploads take common document types by default.** A file question without its own types takes documents, images, spreadsheets, presentations and zip files, not any file a visitor sends. `extensions(['*'])`, or `*` in the form builder, takes any file as before.
- **Page passwords resist guessing from many addresses.** Besides five tries a minute per address, a protected page takes at most 100 wrong passwords an hour from all addresses together (`LYNX_PAGE_PASSWORD_MAX_FAILURES`), then refuses every try for the rest of the hour and logs a warning.
- **Page pickers keep to what editors may view.** The page table, the page picker and the link dialog no longer show or offer pages outside the editor's view scope, even from a crafted request. Pages a field already held still show, so a value an editor can't see doesn't look empty.
- **Exports leave out media of pages guests can't open.** A static export copies the files of pages that are published, out of the trash and open to guests, and no longer the whole media disk.
- **Sitemaps share only a plain guest's build.** A visitor your visibility rules show more to gets a sitemap built for them alone, never cached and sent with `no-store`, so their pages never reach the copy search engines read.
- **The page cache stores only what a plain guest sees.** A page rendered for a visitor a visibility rule treats apart (its `audience()` names a group, or the rule has none) is no longer stored, so a rule based on a header, an address or a cookie can't put its version in the copy every guest gets.

## 0.1.17

Released October 24, 2026. A security release: update soon.

- **Closed by default.** A user without a role has no rights. Before, every signed in user of the app could do everything until someone was given a role, including making themselves a superuser. `lynx:install` warns when nobody is a superuser, and `LYNX_OPEN_UNTIL_ROLES=true` keeps the old behaviour on your own machine. See the [upgrade guide](/prologue/upgrade#from-0-1-16-to-0-1-17).
- **The installer creates your superuser.** `lynx:install` asks for the first superuser's name, email and password when nobody is one, so a new app needs no account made beforehand. `php artisan lynx:superuser` does the same at any time, or makes an existing account a superuser, and `--user` now takes an email too. See [Installation](/getting-started/installation#running-the-installer).
- **Visitors are not the system.** Actions run in a web request with nobody signed in act as a visitor, allowed only what is open to everyone. Code that writes for a visitor uses `lynx()->actingAs(Actor::system(), ...)`.
- **Exports from the admin are a guest's.** A static export started from the admin rendered pages as the signed-in editor, so pages for members and parts of templates for signed-in users ended up in the public copy. Each export now lists and renders pages as a visitor who is not signed in, and leaves the editor's session as it was.
- **Media fields never allow files that run.** A field's extensions can no longer include types a web server or a browser runs (`php`, `phar`, `html`, `svg`, `js` and the like), and fields that listed one stop accepting it. Private files that are not images, video, audio, PDFs or plain text download instead of opening, and every private file is sent with `nosniff` and a sandbox policy.
- **Form answers stay plain text.** In the CSV export, an answer that starts like a formula (`=`, `+`, `-`, `@`) gets a leading quote, so a spreadsheet shows it instead of running it. In the staff email, Markdown in answers is escaped, so a visitor can no longer add links or images to a mail your site sends.
- **The agent reads as the user it works for.** Its read tools (`find_pages`, `get_page`, module tools such as `list_submissions`) ran as whoever ran the code, which in a command or a queued job meant every right. They now run with the roles of the run's actor, like its writes.
- **URL downloads stay on the internet.** `media.store`, which the admin, MCP clients and the agent use, now downloads only from public web servers on the usual ports, checks every redirect, and stops at the field's size limit. Before, it fetched any address, including the server's own network. See [Storing files from code](/basics/images-and-files#storing-files-from-code).
- **Smaller fixes.**
  - Creating a page with the `system` status needs `system.manage`.
  - The invitation screen names the account only for a valid invitation, and is rate limited.
  - The MCP web route takes only Sanctum tokens with the `lynx:mcp` ability.
  - `inspect_seo` and the SEO preview check `pages.view` on the page itself.
  - `describe_schema` needs `pages.view` or `schema.manage`.
  - Read tools that name no permission need `pages.view`.
  - Page references show visitors only published pages that are out of the trash.
  - `media.store` never reads a file path from its input.
  - Moving a field's files on a privacy switch moves only files in the page's own folder.
  - Images that declare more than 50 million pixels are refused before they are decoded.
  - Content package archives with entries outside the package are refused before extraction.

## 0.1.16

Released October 24, 2026.

- **Private files.** A files or images field with **Private** on keeps its files off the public disk and serves them through `/lynx-media/{page}/{file}`, only to people who may open the page: staff who may view it, or visitors when it is published, its page gates let them through and the visibility rules allow it. Switching the setting moves existing files. See [Private files](/basics/images-and-files#private-files).

## 0.1.15

Released October 24, 2026.

- The training site starter runs its tests from a fresh clone: `php artisan test` no longer stops at a missing `tests/Unit` folder.

## 0.1.14

Released October 24, 2026.

- **A new guide: [Build a Training Company Site](/guides/training-site/).** Ten chapters build a bilingual site that brings in leads, takes paid bookings, serves members and runs surveys, from BlatUI's Bloom template. Its code runs in Lynx's test suite, and the finished site is a starter you can clone: [LynxCMS/starter-training](https://github.com/LynxCMS/starter-training).

## 0.1.13

Released October 24, 2026.

- **Survey results.** **Results**, next to each form in the admin, sums the answers up per question: counts per option, averages, date ranges and the latest written answers, with spam left out and a filter by the page a form was sent from. Agents read the same with the new `summarize_form` tool, and code with `FormResults`. See [Forms](/modules/forms#in-the-admin).

## 0.1.12

Released October 24, 2026.

- **Forms that members answer once each.** `Form::oncePerUser()`, or the form builder's **Members only, one answer each** setting, asks guests to sign in and refuses a second answer from the same account, in the visitor's language. See [Forms](/modules/forms#defining-a-form).

## 0.1.11

Released October 23, 2026.

- **JSON translations for regional languages.** A language with a regional locale such as `pt-PT` now reads `__('Book now')` from `lang/pt.json`, as it already read `lang/pt/*.php`. Laravel looks up JSON strings for the exact locale only, so these stayed in English before. `lang/pt-PT.json` still wins where it has a string.

## 0.1.10

Released October 23, 2026.

- **Submissions keep who sent them.** When a signed-in visitor sends a form, the submission records their account, shown in the inbox, the CSV export and the agents' `get_submission`. `Submission::query()->fromUser($id)` finds a member's answers. Run `php artisan lynx:migrate` for the new column.

## 0.1.9

Released October 23, 2026.

- **Structured data mappings from code and AI tools.** `seo.map_template` maps a template to a schema.org type, property by property, and `seo.unmap_template` removes it. Both are MCP and agent tools, and the admin's checks refuse a mapping that would make broken structured data. See [SEO, In code](/modules/seo#in-code).

## 0.1.8

Released October 23, 2026.

- **Family rules no longer block modules.** A home page that limits its children to some templates used to refuse the hidden branch a module installs there, such as menus under `/_menus/`. Pages created with the `system` status are now outside the site's family rules.

## 0.1.7

Released October 23, 2026.

- **List a choice field's options.** `lynx()->fields->choices('level')` gives every option of a choice field, in order, with labels in the current language, for filters, tabs and menus. See [`choice`](/basics/field-types#choice).

## 0.1.6

Released October 23, 2026.

- **Reserved field names say why they are refused.** Creating a field called `status`, `sort` or another name pages and selectors already use now says so, instead of "The selected name is invalid." The [list of reserved names](/basics/templates-and-fields#creating-fields) is in the docs.

## 0.1.5

Released October 23, 2026.

- **Sites whose app signs users in keep staff invitations.** When your app has its own `config/fortify.php`, as a site with sign-ups does, everyone signs in through your app's screens. Lynx now keeps inviting staff and keeps the Account security screen in that case. `LYNX_ADMIN_AUTH=app` chooses it explicitly. See [Who signs users in](/admin/sign-in#who-signs-users-in).

## 0.1.4

Released October 23, 2026.

- **People who sign up get a `guest` role.** Install creates a `guest` role with no grants, and Lynx gives it to each new user when Laravel's `Registered` event fires. A sign-up sees the website and never the admin, even on a site where nobody held a role yet, which used to give every signed in user full rights. `LYNX_REGISTRATION_ROLE` picks another role, or none. See [People who sign up](/admin/permissions#people-who-sign-up).

## 0.1.3

Released October 23, 2026.

- **Restoring a backup into a new database works.** `lynx:import --with-schema` used to fail on sites with a role limited to a branch of the page tree, because it applied roles before the pages they point at existed. It now imports languages, fields and templates, then the pages, then the roles. The whole import runs in one transaction, dry runs included: if any step fails, it lists every error and writes nothing.
- The `content.import` action takes `with_schema`, so code can do the same import as the command.

## 0.1.2

Released October 23, 2026.

- **Cache tab in the page editor.** Every page with a URL shows whether guests get a cached copy, and why not when they don't. Users with `page_cache.manage` also get the template's cache rule and a button to clear the page.
- **Modules can add page editor tabs** with `EditorTabs`.
- **Templates in use can't be deleted.** The template screen lists the pages that use the template, each linking to where its template is switched. The `templates.delete` refusal names those pages, so scripts and agents know what to do.
- **One-language sites see no language wording.** The admin hides the Translatable checkbox, "(default language only)" notes and similar text until a second language exists. Text, textarea and URL fields are created translatable, so adding a language later just works. `lynx()->languages->isMultilingual()` gives modules the same check.
- **Options fields explained.** The field form describes how `options` differs from `choice`, and the forms module hides `options` from the type menu with the new `FieldTypeChoices`.
- The admin's CSS and JavaScript are built when packages are split instead of being committed, so `lynxcms/admin` still ships them and needs no build step.

## 0.1.1

Released October 23, 2026.

- **Module screens work with cached routes.** After `php artisan route:cache`, the page cache, page password and sitemap screens answered 500. Modules now register screens with `Admin::screen()`, which the route cache keeps.
- **Pending module migrations are announced.** After `composer require` of a module with migrations, Composer prints which package has database changes waiting. The new `php artisan lynx:migrate` lists them, asks whether the database is backed up, and runs only Lynx's migrations.

## 0.1.0

Released October 22, 2026. The first public release: core, admin, MCP server, AI agent and the menus, media, forms, SEO, sitemap, page cache, page password and static site modules, on Packagist.
