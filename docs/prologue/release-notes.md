---
title: Release Notes
description: What changed in each Lynx release.
order: 1
---

# Release Notes

Every `lynxcms/*` package shares one version number, and only classes marked `@api` are public API. The [upgrade guide](/prologue/upgrade) lists what to do between releases.

## Unreleased

- **Keyboard shortcuts** in the admin: `?` lists them, `/` searches, `g` then a letter opens a screen (`g h` Home, `g p` Pages and the rest), and `[` and `]` step through the page editor's tabs. Modules give their screens a letter with `Navigation::add(shortcut:)`. See [Keyboard shortcuts](/admin/#keyboard-shortcuts).
- Empty lists say what would be there and how it gets there, with the action that adds the first one (a new field, a new role), and lists a filter emptied offer to clear it (Fields, the forms inbox). Modules use the same `<x-lynx-admin::empty-state>`. See [Extending the admin](/admin/extending).
- The admin opens on a **Home** screen: a welcome with today's date and buttons for common tasks, stats (pages, pages not published, languages, form submissions), the pages with a draft, the next scheduled changes, the pages changed last and the newest unread submissions, each limited to what the user may view. Each list's heading opens the page tree's filter, which a link can now open (`/lynx/pages?filter=drafts`). Modules and apps add stats, buttons and panels with `HomeScreen`. See [The admin](/admin/) and [The home screen](/admin/extending#the-home-screen).
- Fixed: `LYNX_TRAILING_SLASH=1` (and `value="true"` in `phpunit.xml`, which arrives as `"1"`) now turns trailing slashes on; only the word `true` did before. A site that published `config/lynx.php` should cast the value there too: `(bool) env('LYNX_TRAILING_SLASH', false)`.
- Text fields and plain or Markdown textarea fields with a `max_length` show a character counter under the input in the page editor, such as 48/60, red past the limit.
- **Duplicate a field** from the Fields screen, or with `lynx()->fields->duplicate()` (the `fields.duplicate` action): a new field with the same type, labels, translation and settings under a new name. See [Duplicating a field](/basics/templates-and-fields#duplicating-a-field).
- A template's **Family** lists (child, parent and switch templates) are chosen with a search by label or name instead of a checkbox per template, which grew unwieldy on sites with many templates.
- The page editor's header is shorter: **SEO preview** now shows on the SEO tab, below the fields, and the template name and **Add child page** are gone (add a child from the **Children** tab or the page tree). The **Settings** tab links to the page's template, in a new browser tab, for those who manage templates. Modules can add views below a tab's fields (`AdminSlots::editorTab()`). See [Extending the admin](/admin/extending#below-a-tab-s-fields).
- Menus keep site pages out: menu items only hold menu items, and menus only menu items (template family rules), so moving a page under a menu item is refused everywhere, as it would take the page off the site. Sites get the rules the next time a menu is saved. Page searches in the admin now say where a page without a URL lives, such as "in Main navigation".
- In debug mode, a page whose template has no view says which view to create and how to start it with `lynx:make:view`. See [Views](/basics/views#how-a-page-is-rendered).
- `php artisan lynx:templates` and `lynx:fields` list the schema like `route:list`: templates with their pages, fields and views, a template's fields in order, and every field with where it is used. `--json` is for scripts and agents. See [Artisan](/reference/artisan).
- `php artisan lynx:ide-helper` writes `_ide_helper_lynx.php`, so PhpStorm and VS Code autocomplete `$page->field` and `$item->field` with the types `lynx:phpstan` gives them. See [Autocomplete in your editor](/deeper/custom-field-types#autocomplete-in-your-editor).
- `php artisan lynx:make:view` writes a starting view for a template, with each field printed the way its type reads; `--all` writes the missing ones. See [Views](/basics/views#starting-a-view).
- `php artisan lynx:doctor` looks for problems in a site's setup and structure: templates whose pages have no view, unused fields, orphaned values, a scheduler that does not run and a missing `storage:link`, each with a fix. It fails on errors, so deploy scripts can stop on one; `--json` is for scripts and agents. Modules add their own checks (`DoctorChecks`). See [Artisan](/reference/artisan#checking-a-site).
- **Duplicate** a page from the page tree or its Settings tab, alone or with the pages below it (`lynx()->pages->duplicate()`, the `pages.duplicate` action). The copy starts unpublished with **[Copy]** before its title, gets its own copies of images and files, and references between copied pages point at the copies. See [Publishing](/basics/publishing#duplicating-a-page).
- Pages with children have a **Children** tab in the page editor: search, filter by status, drag to reorder or sort them all by title or date (`lynx()->pages->sortChildren()`, the `pages.sort-children` action), and publish, hide or trash children one at a time or several at once, or move several under another parent. See [The admin](/admin/).
- A template's section can be copied to other templates, with its fields and their settings: the copy button on a section in **Settings › Templates**, or `lynx()->templates->copySection()`. A template that already has one of the fields can get renamed copies of them instead. See [Copying a section to other templates](/basics/templates-and-fields#copying-a-section-to-other-templates).
- Fixed: `LYNX_PAGE_CACHE=1` now turns the page cache on, and `LYNX_FRAGMENT_CACHE=0` turns the fragment cache off, as does `LYNX_PAGE_CACHE_DEBUG` for its headers. Only the words `true` and `false` worked before. A site that published `config/lynx-cache.php` should cast the three values there too, such as `(bool) env('LYNX_PAGE_CACHE', false)`.

## 0.1.41

Released November 6, 2026. Rich text fields can turn on extra tools and show their HTML, and Review changes understands drafts. Nothing to do after updating, and no database changes.

- Rich text fields can turn on extra editor tools in their settings: superscript and subscript, underline and highlight, code and code blocks, a horizontal line and text alignment. All are off by default. See [`textarea`](/basics/field-types#textarea).
- Rich text fields have an **HTML** button: it shows the field's markup to read or edit. What you type there is saved as is, cleaned like any rich text; back in the editor, markup the editor doesn't know goes once you edit the text there.
- **Review changes** shows rich text formatted, with **Show HTML** to compare the markup.
- **Preview** in the page editor is one button for the default language, with the page's other languages in its menu.
- **Review changes** with a draft open lists what the draft, with any edit not saved yet, would change on the live page, and offers to publish it. Before, it only looked at unsaved edits, so a saved draft had "no changes to review".
- A language's settings screen names the language in its heading and breadcrumb (Languages › Español), or gives its code when it has no name.
- **Fix:** with a module that ships its own admin stylesheet (such as the translate module), the admin's sidebar could disappear: the module's copy of a class like `hidden` overrode the admin's `md:flex`. `module.css` now puts a module's classes below the admin's. Module authors rebuild their stylesheet against 0.1.41.
- **Fix:** a language's **Falls back to** list showed the default language twice, as "The default language" and by name. It now shows it once: "The default language (English)".
- **Fix:** the admin's input style (fill or inset, under appearance) now also applies to native fields styled with BlatUI's classes (`blat-input`, `blat-textarea`, `blat-select`), which most admin screens use, not only to the form components. A few controls with classes of their own now use BlatUI's.

## 0.1.40

Released November 5, 2026. Modules in their own repositories can style their admin screens, and keep the site's look. Nothing to do after updating, and no database changes.

- Modules in their own repositories can ship an admin stylesheet: build it from `resources/css/module.css` and register it with `AdminAssets`. It follows the site's appearance settings and branding. See [Styles for your screens](/admin/extending#styles-for-your-screens).

## 0.1.39

Released November 5, 2026. Machine translation reaches pictures' descriptions, form options and template texts, and modules can wrap every translation provider. Nothing to do after updating, and no database changes.

- `TranslationProviders::extend()` wraps every translation provider, so a module can count characters or pass a glossary to all of them. Providers get documented options: `glossary`, `formality` and `context`, which the `laravel-ai` provider now follows.
- `pages.translate` also fills the texts inside a page's values that have none in the target language: a picture's description, a form question's options, and the page's own template texts. A template text whose translation loses a placeholder is left for a person. Field types holding texts per language implement `HasTranslatableTexts`.
- `PageStrings::untranslated()` lists a page's or the site's template texts with no text in a language yet, and modules can add components to the Site texts screen with `AdminSlots::SITE_TEXTS_HEADER`.

## 0.1.38

Released November 5, 2026. Find the pages whose machine translations are outdated, and read what each page tree filter finds. Nothing to do after updating, and no database changes.

- The selector clause `has_outdated=pt` finds pages with a machine translation into Portuguese whose source text has changed, on the page or in its draft. A field can no longer be named `has_outdated`.
- The page tree's filters explain themselves: hover over one, or focus it, to read what it finds.
- Modules can add filters to the page tree's Details view with `TreeFilterRegistry`, each with an optional hint. See [Extending the admin](/admin/extending#page-tree-filters).

## 0.1.37

Released November 5, 2026. Translation into drafts, with a choice of provider, and a way to tell when a machine translation's source has changed. Modules can add buttons to the page editor. Run `php artisan lynx:migrate` after updating.

- `pages.translate` can write into the page's draft (`draft: true`), so an editor reviews machine translations before visitors see them, and can name the provider to use (`provider:`). Providers register by name in `TranslationProviders`; `lynxcms/ai` registers `laravel-ai`. See [Translation](/ai/#translation).
- A dry run of `pages.translate` no longer calls the provider: it says how many texts and characters it would send, so checking costs nothing.
- `LynxCms\Core\Support\Value` is now public API.
- A machine translation now knows when the text it was translated from changes: `lynx()->pages->translationStates($page, 'pt')` says, for each field, who wrote it and whether it is outdated. Run `php artisan lynx:migrate`: the value metadata gains two columns. See [Translation](/ai/#translation).
- Modules can add their own Livewire component to the page editor's header with `AdminSlots`, and tell the editor to reload a draft they changed with `lynx-draft-changed`. See [A button in the page editor](/admin/extending#a-button-in-the-page-editor). A view under each field, in each language, goes in `AdminSlots::EDITOR_FIELD` with `addView()`.
- **Fix:** publishing a draft in the page editor failed with "Unable to resolve dependency [int $revision]", and restoring a revision kept the old values in the editor's form. Both now tell the editor the page's new revision, and a restore reloads the fields.
- **Fix:** a machine translation saved into a draft (`pages.save_draft` with `origin`), or already on the page, became a human value once the draft was published. Publishing now keeps each value's origin, so a later translation may still replace it and human text stays protected. A person's edit in the draft still publishes as human.

## 0.1.36

Released November 3, 2026. Rolling migrations back in development works on PostgreSQL and MySQL again, and many more of Lynx's commands and actions are tested. Nothing to do after updating, and no database changes.

- **Fix:** rolling back every migration in development (`migrate:reset`, `migrate:refresh`) failed on PostgreSQL and MySQL: the admin's sign-in migration left the `passkeys` table it creates, whose key on `users` stopped the app's users migration. It now removes the `passkeys` table and the two-factor columns it added, and leaves them when the app's own Passkeys or Fortify migration made them. A test now runs every migration down and up again on SQLite and PostgreSQL. The [upgrade guide](/prologue/upgrade#updating) says to restore a backup instead of rolling back on a live site.

## 0.1.35

Released November 3, 2026. A clearer admin sidebar, a template's field list without module fields, and full size pictures in the images field. Nothing to do after updating, and no database changes.

- **The current screen stands out in the sidebar.** Its link is tinted with the admin's accent colour instead of a grey close to the sidebar's own, and the blue mark at its end is gone. Hover keeps the light grey, so the two no longer look alike. A theme can change the tint with `--lynx-sidebar-active-mix`.

- **Fix:** a template's **Add a field** list offered the fields modules keep for themselves (`menu_*`, `form_*`, `seo_*`, the page cache's and the sitemap's). It now offers the site's own fields only, and adding a module's field is refused. Fields already on a template, such as the SEO fields, stay.

- **Zoom a picture.** Each picture in an images field has a zoom button that opens it uncropped, at the largest size the screen allows. It also works on fields the editor may only look at.

## 0.1.34

Released November 2, 2026. One query fewer on most pages. Nothing to do after updating, and no database changes.

- **The home page is found once per request.** `lynx()->pages->get('/')` looked its id up every time, and a layout and the SEO module both ask for it on every page. The id is now kept until the next action.

## 0.1.33

Released November 2, 2026. Pages read well in Tinkerwell, `dump()` and `dd()` too. Nothing to do after updating, and no database changes.

- **Fix:** Tinkerwell, `dump()` and `dd()` still printed every template and field of the site with a page, about 2,000 lines for one page. 0.1.32 only reached tinker, whose casters Tinkerwell doesn't read. A page no longer holds a way to the loader and the registries, so every dumper stops at the page: about 90 lines, its properties and then its own data by language.

## 0.1.32

Released November 2, 2026. Pages read well in tinker. Nothing to do after updating, and no database changes.

- **Pages read well in tinker.** A page shows its own data: its names, the values loaded so far by language, and the fields not loaded yet. Before, it also printed the loader it keeps for those fields, with every template and field of the site. `var_dump()` and `print_r()` show the same. Tinkerwell, `dump()` and `dd()` still printed the loader; 0.1.33 fixes that.

## 0.1.31

Released November 2, 2026. Cached fragments and translated fields cost less to read. Nothing to do after updating, and no database changes.

- **Cached fragments cost one cache read.** A `@lynxCache` block already in the cache also read the cache's revision, which only a block that renders needs. On the corporate starter's home page, with the cache store in the database, that was 10 of its 70 queries.

- **Translated fields read faster.** Since 0.1.22, reading a translatable field worked out the language's fallback chain twice, building it from scratch each time. The chain is now kept for the request, which halves the cost of a read and brings the workbench's course list back to about 35 ms over HTTP, from 38.

## 0.1.30

Released November 2, 2026. Rendered pages run fewer queries, and forms can load when visitors reach them. No database changes. The [upgrade guide](/prologue/upgrade) says what to check.

- **Page fields check their pages together.** Showing visitors the pages a page field picks cost two queries for each of those pages: one for the pages above it, one for the trash. It now costs none while no unpublished page has children, and one otherwise. The corporate starter's home page lost 43 queries.

- **Templates share their fields.** Each template built its own copy of every field it uses. There is now one copy of each field, so the corporate starter loads 117 models instead of 307, and its templates in 0.5 ms instead of 6.7.

- **The SEO settings stay in the cache.** Every page read about ten fields of the SEO settings page. They are now kept in the app's cache, one copy per host and language, until the settings page, a language, a field or a template changes (ADR-0098). A save in the admin still shows on the next page.

- **Forms can load lazily.** `<x-lynx-form :form="$page->form" lazy />` renders a placeholder and loads the form once the visitor scrolls to it. The tag now always hands the form the page's language, so a lazy form loads in it too.

- With the first three, the corporate starter's home page runs 73 queries instead of 129, and renders in 50 ms instead of 58 on SQLite, 84 instead of 103 on PostgreSQL.

- The Server capacity page is now [Deployment](/deeper/deployment), with a Performance section measured on the corporate starter: keep sessions in files on SQLite (four to six times as many pages a second with 10 visitors at once), cache the configuration, and keep the cache store out of the database. The installation guide asks for file sessions on SQLite.

## 0.1.29

Released October 31, 2026. Forms can take a single file again. No database changes.

- **Fix:** a form's file field that takes one file, the default, could not be sent. Visitors read that the field "must be an array". Fields that take several files were not affected.

- **Fix:** errors on a file or a checkbox showed the form's key, such as `data.cv.0`. They now name the field by its label.

- The installation guide has a section on [the queue worker](/getting-started/installation#the-queue-worker): form emails wait in the queue until one runs.

## 0.1.28

Released October 31, 2026. New sites start on a plain page template. No database changes. The [upgrade guide](/prologue/upgrade) says what to check.

- **A template for a site's first pages.** `lynx:install` creates `simple-page`, with only a title, and the New page screen preselects it until a parent has pages of its own to go by.

- **Fix:** the first page on a new site defaulted to the `not-found` template. That template is no longer offered for new pages.

- The installation guide says when to run `storage:link`, and the meeting rooms guide links its [live demo](https://meeting-rooms.lynxcms.org/).

## 0.1.27

Released October 31, 2026. A security release for sites with roles limited to some templates or branches: update soon. Sites whose editors all have full page rights are not affected. No database changes. The [upgrade guide](/prologue/upgrade) says what to check.

- **Site texts need rights on the home page.** `strings.save` checked only the page it named, so a role limited to a branch could change site texts through MCP or the agent. It now needs `pages.edit` on the home page, as the Site texts screen always did.

- **The link dialog keeps to what the editor may view.** A crafted request could read titles, URLs, headings and file names of pages outside a role's scope, including unpublished ones.

- **The Cache tab stays on its own page.** A crafted request could read another page's status and stored URLs.

## 0.1.26

Released October 31, 2026. The admin and every module now speak the editor's language, with English and Portuguese included. No database changes. The [upgrade guide](/prologue/upgrade) says what to check.

- **The admin in each editor's language.** Open the menu with your name in the sidebar and pick a language under **Language**. The choice belongs to the account, so a Portuguese editor can work on an English site. Editors who haven't chosen see `LYNX_ADMIN_LOCALE`, or the app's locale when that is empty. See [The admin's language](/admin/#the-admin-s-language).

- Every screen is translated: sign-in, the sidebar, the page tree and editor, fields, templates, languages, roles, users, and the menus, media, SEO, sitemap, page cache, static export, forms and page password modules.

- To offer another language, list it in `lynx-admin.locales` and add its files in `lang/vendor/lynx-admin/{code}`. Any line of Lynx's wording can be reworded the same way, and each module has a folder of its own, such as `lang/vendor/lynx-forms`.

- Modules can pass translation keys, or plain text as before, to `Navigation`, `EditorTabs` and `FieldKinds`. `FieldKinds::groupLabel()` names a group in the admin's language.

- The invitation email goes out in the language of the person who sends it.

- The dropdown menu's **Menu** and **Close** buttons ship in Portuguese, and your own `lang/{code}.json` still rewords them.

- Field and template labels still follow the language pages are edited in, and validation errors from actions stay in English, since agents read them too.

- **Fix:** page settings said "the current URL will redirect" on every page. It now says so only when the name changes.

- **Fix:** the Site texts screen showed "Text" where it meant to show the `@string('key', 'Text')` example.

- The training starter's `composer setup` links the storage folder, so a first uploaded photo no longer shows as a broken image, and the starter asks for PHP 8.5.

## 0.1.25

Released October 28, 2026. Page fields hold as many pages as each use allows, in the order editors set, and check their templates on every save. Drafts can go live at a set time, and selectors leave out pages without a URL. No database changes. The [upgrade guide](/prologue/upgrade#from-0-1-24-to-0-1-25) lists two things to check.

- **Selectors leave out pages without a URL**, such as hero slides, promos, forms and settings pages, unless they ask for them: by naming their template, by reading below such a page (`children()` of a slide branch), by `id=`, or with `include=no_url` or `include=all`. A site search no longer finds a slide that links nowhere. See [Pages without a URL](/basics/selectors#pages-without-a-url) and the [upgrade guide](/prologue/upgrade).

- **Drafts can go live by themselves.** `lynx()->pages->schedule($page, publishDraftAt: ...)`, the `pages.schedule` action's `publish_draft_at`, or the new **Publish the draft at** field in the Settings tab sets when a page's draft is published. The scheduler publishes it as an editor would, and the page's activity says "Published the draft on schedule". See [Scheduled publishing](/basics/publishing#scheduled-publishing).

- Page fields take `min` and `max`, as repeaters and matrices do: "at most 8 cards", "at least 2 related courses". Saving more or fewer fails with "Pick at most 8 pages.", from the admin, the API, MCP and the agent alike, and the admin's page search steps aside once the field is full. A template, or a block type of a matrix, can set its own `min`, `max` and `templates` for a page field, so one `cards` field holds 4 pages in one section and 8 in another; repeaters and matrices take a template's own `min` and `max` too. See [Field Types](/basics/field-types#page).

- In the admin, the pages a page field holds are a list editors put in order: drag a page by its handle, or move it with the arrows beside it. Templates read them in that order, as before.

- A page field's `templates` now holds on every save, not only in the admin's picker: a save that adds a page of another template, from code, the API, MCP or the agent, fails with "Pick pages that use course: [news] uses newsroom.". Pages a field already holds stay, so narrowing `templates` later never blocks an unrelated save. Page tables work the same way; before, they refused a save that kept an item of a template the field no longer listed.

- `PageArray::publishedIn()` keeps the pages published in the visitor's language, or in one you name, for pages that feature others per market. Lists, searches and page fields keep the others and link to their default-language address; menus leave them out. See [Languages](/basics/languages#urls).

- **Fix:** a page taken offline in one language linked to its address in that language, which answered 404, from lists and page fields in that language. It links to its default-language address, as a page never published there does.

- The example that stores a file from code on [Images and Files](/basics/images-and-files#storing-files-from-code) puts the stored entry in the file's `values` and starts from an empty list, so it runs as written. Before, it failed with "Each file needs its stored name".

## 0.1.24

Released October 28, 2026. A clearer admin: the Lynx logo and Geist, a page editor with a side panel and a two-button header, a Details view of the page tree, and rebuilt field and page cache screens. It requires Livewire 4.4.8, which fixes a security issue. No database changes. The [upgrade guide](/prologue/upgrade#from-0-1-23-to-0-1-24) lists two things to check.

- `lynxcms/admin` requires Livewire 4.4.8 or later, which fixes an authorization bypass in Livewire's `#[Authorize]` attribute ([GHSA-mhf7-xp6r-6qrc](https://github.com/advisories/GHSA-mhf7-xp6r-6qrc)). Lynx does not use that attribute, but your app's own components may. The update command in the [upgrade guide](/prologue/upgrade#updating) brings both.

- Lynx has a logo. Until a site sets its own name or logo, the admin's sign-in screens and sidebar show the Lynx mark instead of an "L", and browser tabs show the Lynx icon. Sites with their own branding look as before. See [The site's name, logo and colours](/admin/#the-site-s-name-logo-and-colours).

- The admin's default font is Geist, for headings too, instead of an Inter body and monospace headings. Set `LYNX_ADMIN_FONT=inter` and `LYNX_ADMIN_HEADING_FONT=mono` to keep the old look, or give headings any other font.

- The admin wears the Lynx mark's facet: the language tabs mark a field's state with a small slanted shape (filled, light or outlined) instead of a dot, and the sidebar ends the current screen's link with one. The sidebar takes a trace of the accent colour.

- The page editor's header says under the title whether the page is published, hidden or unpublished, and marks a waiting draft with links to preview it. The live page and the links modules add (such as **SEO preview**) sit there too. On the right, at most two buttons: **Save** (or **Save draft**) with a menu holding **Save as draft**, **Review changes** and **Discard draft**, and **Publish draft** while a draft waits. The blue "You are editing a draft" box is gone; the header's **Draft changes** says it.

- The page editor puts every label above its field, and shows a panel beside the fields: publishing state, how much of the page each language has its own text for, and the latest activity. Templates with their own editor sidebar keep it instead.

- The page tree has a **Details** view beside the default one: each page's state in every language, its status and its last change, with who made it. Filters above it list the pages with a draft, the scheduled ones and those not yet in each language, with their counts. Each user's choice lasts for the session. Trees built on the page tree, such as the menu builder, keep the simple view.

- The page's activity, in the editor's panel and on its Settings tab, says what happened in plain words: "Changed Title (Português) and Summary", "Unpublished and locked the page", "Moved under Courses", "Published the draft". Module actions that save a page, such as a menu item or a form, and the scheduler's changes read the same way: "Published in Português on schedule". The scheduler now records, for each change it runs, whether it published or unpublished and in which language. A run that changes several pages files an entry under each page instead of only the first, and a run with nothing due no longer adds an empty entry to the log every minute. Your own actions can ask for the same with `logsActivityPerPage()`. Other module actions keep their own summary.

- Saving or discarding a draft now shows in the page's activity. Both were filed under the draft, which no screen lists.

- **Selectors find pages by state.** `status=unpublished` (or `hidden`, `locked`) finds pages with that status, `language!=pt` finds the pages not published in Portuguese yet, `has_draft=1` the pages with a draft waiting, and `has_schedule=1` the pages with a publish or unpublish date set. See [Finding Pages](/basics/selectors#status). `has_draft` and `has_schedule` are now reserved field names.

- **Field screens for people.** A new field starts with what it holds, in plain words and groups ("Paragraphs", "Blocks", "Repeater"), then its label, with the name templates use made from it. New fields start with their own text in each language; kinds that have none, such as numbers, are the same in every language. Each field's screen has tabs: a repeater's or matrix's fields or block types, **Preview** (the field as editors see it), **Field**, and **Limits and display**. Settings have labels of their own ("Longest text", "Editors write in"), formatters are switches, and the add button's text can be set in every language. Save is at the top, and Delete on the Field tab. Modules name their field types with `FieldKinds`; see [Extending the admin](/admin/extending#field-types-the-field-form-offers).

- The admin sets up matrix and repeater fields. A matrix's screen shows its block types as cards, each with its label, slug, block header and fields, and a picture of the block as editors will see it. Fields are added from the site's fields, or created on the spot, and sorted by dragging. Removing a block type that pages use asks first. Before, these settings were a single text box that could not hold them.

- Choice fields take a `default`: new pages and new repeater or matrix items start with that option chosen, from the admin, the API, MCP and the agent alike. Existing pages keep their values. In the admin, tick **Default** next to an option; a required select with a default no longer offers an empty option. See [Field Types](/basics/field-types#choice).

- The page picker shows the search box first and the chosen pages below it. Results float above the page, open upwards near its bottom, and can be reached with the arrow keys.

- The page cache screen has tabs: **Overview** (stored pages, in all and per template, and clearing), **Rules** (each template's rule summed up on its row, opening to edit it), **Page parts** (fragments, explained for site owners) and **Web server setup**. The tab stays in the address, so a link can open one.

- **Pages without a URL** (forms, settings pages) get a Settings tab that fits them: one **Name in templates** instead of a name and a published switch per language, one schedule for the whole page, no Hidden status and no old URLs. Pages a module manages, such as forms, no longer offer a template or parent to change. **Unique** shows only on pages without a URL that already have it, so it can be turned off: without a URL it only keeps other pages from the name. When a unique page without a URL holds a name, the refusal names it by its title instead of "/".

- The form setting **Members only, one answer each** is now **Signed-in visitors only, one answer each**, with help saying it needs visitor accounts. Installing the form builder again, or creating a form, brings the new wording to existing sites.

- **Fix:** a form with a name of its own in another language disappeared from that language's pages, since its key came from the name in the visitor's language. A form's key is now its default-language name everywhere; names already set in other languages stay, unused.

- The sentences under a field that explain a fallback ("visitors see the English value") are now translation hints, off by default. Turn them on under **Translation hints** in the user menu; each browser remembers the choice.

- The page tree's row actions are plain text, and **Unpub** and **Pub** read **Unpublish** and **Publish**.

- On a template's screen, the buttons that remove a field or a section work again.

- Listing a field that does not exist in a repeater, a matrix or a template fails with a message naming `lynx()->fields->add()`, not only the `fields.create` action.

- A matrix without block types, or a block type or repeater without fields, fails with "Add at least one block type." or "List at least one field." instead of naming the setting's path.

- The matrix example on [Field Types](/basics/field-types#matrix) creates its fields first, so it runs as written.

## 0.1.23

Released October 26, 2026. Run `php artisan lynx:phpstan` after updating if you use the PHPStan extension (see the [upgrade guide](/prologue/upgrade)).

- `lynx:phpstan` types text fields as they read: `TranslatableValue|null` for translatable fields and `SharedValue|null` for other texts and textareas, since empty ones read as `null`.

- PHPStan analyses again after `lynx:phpstan` rewrites `lynx/phpstan/types.php`, instead of reporting fields you just added as undefined until `phpstan clear-result-cache`. This needs PHPStan 2.3 or later; older versions behave as before.

- `trans_choice()` on a language with a regional locale such as `fr-CA` reads the plural forms from `lang/fr`, and picks them with French plural rules. Before, it checked `lang/fr-CA` only and showed the fallback language's plurals, and a hyphenated locale always took the first form.

- Structured data for people takes `worksFor`: map it to `@organization` for the site's own organization, to a page field, or give `worksFor.name` and `worksFor.url`.

## 0.1.22

Released October 26, 2026. Nothing to run after updating. If your code reads other languages through a translatable field, see the [upgrade guide](/prologue/upgrade).

- **Friendlier commands in a terminal.** `lynx:superuser` checks the email and password as you type them. `lynx:import`, `lynx:export`, `lynx:find` and `lynx:ask` ask for a missing argument, `lynx:page-cache` without flags lets you pick what to do, and `lynx:import --prune` and `lynx:schema:apply --force` show what they would remove and ask first. Long commands (`lynx:backup`, `lynx:export`, `lynx:import`, `lynx:static`, `lynx:demo`, `lynx:bench`, cache and sitemap warming, the agent's planning) show a spinner or a progress bar. Commands run from code, a pipe, a scheduler log or with `--no-interaction` never ask and print plain lines, as before.

- A translatable text reads as `null` when no language in the visitor's fallback chain has a value, even if another language holds one, so `@if ($page->job_title)` and `$page->job_title ?: $default` work on pages translated only in part. Before, such a field read as an empty object, which PHP treats as true.

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
