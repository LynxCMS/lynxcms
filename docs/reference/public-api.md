---
title: Public API
description: Every class marked @api, the code apps and modules may rely on.
---

# Public API

Classes marked `@api` are the ones apps and modules may rely on. From 1.0 on, a minor release does not change them in a way that breaks code that uses them ([Versioning](/prologue/versioning)). Every other class is `@internal`: Lynx's own packages use it, and it may change in any release. Your IDE shows the marker on each class's docblock.

This page lists the 268 `@api` classes, interfaces, traits and enums by package. It is written from the code by `composer docs:api`, and a test fails when it falls behind.

## lynxcms/contracts

In `LynxCms\Contracts`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\Action` | interface | A single write operation. |
| `Actions\ActionHalted` | class | Thrown by a before-hook to stop an action. |
| `Actions\Actor` | class | Who performed an action: a logged-in user, a visitor who is not signed in, the CLI, an MCP client, the built-in AI agent or the system (queued jobs, scheduled tasks, code that asks for it). |
| `Fields\Comparison` | enum | How selectors compare a field's stored "data" column: as text (with %=, ^=, $=), as integers or decimals, as booleans, as dates ("today", "-7 days", "2026-01-01"), or not at all. |
| `Fields\FieldType` | interface | A field type owns its storage, its value conversion and its validation. |
| `Fields\FormatContext` | class | What a formatter knows about the value it formats. |
| `Fields\Formatter` | interface | A text formatter: attached to text fields in an ordered list, run when a template outputs the value, never on the stored value. |
| `Translation\TranslationProvider` | interface | Machine translation behind one interface: a model through laravel/ai, DeepL, a local model. |

## lynxcms/core

In `LynxCms\Core`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\Action` | class | Base class for Lynx actions. |
| `Actions\ActionRegistry` | class | Every registered action. |
| `Actions\Content\ImportContent` | class | Imports a content package through pages.save: pages are matched by key, created when missing and updated when they differ; page references, and files stored for the page they land on, are set once every page exists. |
| `Actions\Fields\CleanHtmlValues` | class | Runs the stored values of html fields through their field type again, so HTML saved before the sanitizer (or before a stricter one) is cleaned the way a save cleans it today. |
| `Actions\Fields\CreateField` | class | Creates a field and its table at runtime. |
| `Actions\Fields\DeleteField` | class | Deletes a field, removes it from every template and drops its table with all values. |
| `Actions\Fields\DuplicateField` | class | A new field like another: its type, labels, translation and settings under a new name, made with fields.create (which checks the name). |
| `Actions\Fields\MakeFieldTranslatable` | class | Makes a shared field translatable: each value, on pages, their drafts and the items of repeaters and matrices that show the field, becomes the default language's, which other languages fall back to. |
| `Actions\Fields\UpdateField` | class | Changes a field's label or settings. |
| `Actions\InstallLynx` | class | Creates what every Lynx site has: the default language (code "default", name "Default", no prefix), the root page, a plain template for a site's first pages, and a hidden "Page not found" page shown for URLs that match nothing, unless someone already chose one or none. |
| `Actions\Languages\CreateLanguage` | class | Adds a language at runtime. |
| `Actions\Languages\DeleteLanguage` | class | Removes a language and every value, name and path stored in it. |
| `Actions\Languages\SetCurrentLanguage` | class | Sets the language for the rest of the request and Laravel's locale. |
| `Actions\Languages\UpdateLanguage` | class | Renames a language or changes its code, locale, fallback, prefix or active state. |
| `Actions\Pages\DeletePage` | class | Permanently deletes a page and everything under it (trash comes later). |
| `Actions\Pages\DeleteRedirect` | class | Forgets an old path of a page, so it stops redirecting (and becomes free for other pages without a lookup tie). |
| `Actions\Pages\DiscardDraft` | class | Deletes a page's draft; the live page stays as it is. |
| `Actions\Pages\DuplicatePage` | class | Copies a page with its values in every language, its template strings and, when asked, every page below it. |
| `Actions\Pages\EmptyTrash` | class | Deletes trashed branches for good: all of them, or one. |
| `Actions\Pages\LockEdit` | class | Takes or renews the edit lock on a page, so nobody else saves it while it is open in an editor. |
| `Actions\Pages\MovePage` | class | Moves a page (and its subtree) under another parent. |
| `Actions\Pages\PublishDraft` | class | Makes a page's draft live: its values and its own template strings are saved onto the page through pages.save, so validation, hooks, activity and a revision apply, and the draft is deleted. |
| `Actions\Pages\PurgeOrphanedValues` | class | The only action that deletes field values left behind by template changes. |
| `Actions\Pages\RebuildPageReferences` | class | Compares the page references index with every page's stored values and fixes the rows that differ. |
| `Actions\Pages\RestorePage` | class | Takes a branch out of the trash: back where it was, with its names and URLs, or under a parent given when that place is gone or in the trash itself. |
| `Actions\Pages\RestoreRevision` | class | Puts a page's values back as they were in a revision, as a new pages.save, so the current values become a revision too. |
| `Actions\Pages\RunSchedules` | class | Applies the scheduled publishing that is due, and drafts due to go live, oldest first. |
| `Actions\Pages\SaveDraft` | class | Saves field values and the page's own template strings to the page's draft, creating it from the live page the first time. |
| `Actions\Pages\SavePage` | class | Creates or updates a page: names per language, field values per language, status, and the page's own template strings. |
| `Actions\Pages\SchedulePage` | class | Sets when a page is published or unpublished in one language, or in every language with language "*". |
| `Actions\Pages\SetNotFoundPage` | class | Picks the page shown, with status 404, for URLs that match nothing, or none, for Laravel's own 404. |
| `Actions\Pages\SetPageStatus` | class | Adds or removes status flags without saving field values, so a page with an incomplete required field can still be hidden, published or locked (the page tree's quick actions). |
| `Actions\Pages\SortChildren` | class | Puts every child of a page in one order at once: by title, or by when they were created, last changed or published, each either way. |
| `Actions\Pages\SortPage` | class | Changes a page's position among its siblings. |
| `Actions\Pages\SwitchTemplate` | class | Switches a page to another template. |
| `Actions\Pages\TranslatePage` | class | Machine-translates a page into a language: only translatable fields that have no value there (unless overwrite), in one batched provider call. |
| `Actions\Pages\TrashPage` | class | Moves a page and everything under it to the trash. |
| `Actions\Pages\UnlockEdit` | class | Releases the edit lock on a page: the holder's own, or anyone's for a superuser (someone left a page open and went home). |
| `Actions\Roles\AssignRoles` | class | Sets the roles of a host app user, replacing the ones they had. |
| `Actions\Roles\DeleteRole` | class | Deletes a role, its grants and who held it. |
| `Actions\Roles\SaveRole` | class | Creates or updates a role and replaces its grants. |
| `Actions\Runner\ActionContext` | class | Passed to every action and hook: who is acting, whether this is a dry run, and the change recorder. |
| `Actions\Runner\ActionResult` | class | What the runner returns: the action's return value, the changes and whether it was a dry run. |
| `Actions\Runner\ActionRunner` | class | Runs every write in Lynx: validation, transaction, before-hooks, the action, after-hooks, dry-run rollback and domain events after commit. |
| `Actions\Runner\Change` | class | One change an action made (or would make, in a dry run). |
| `Actions\Runner\ChangeSet` | class | The changes recorded during one outer action, including nested actions. |
| `Actions\Runner\HookBuilder` | class | Fluent helper returned by Lynx::hook(SavePage::class). |
| `Actions\Runner\HookRegistry` | class | Before/after hooks on actions, the main extension point for modules. |
| `Actions\Runner\Input` | class | Validated input of an action: an associative array with typed accessors. |
| `Actions\Schema\BuildDraft` | class | Builds a draft of languages, templates and fields for the MCP server and the agent: the database schema only, never files, so an agent can't rewrite a site's schema files, views or types. |
| `Actions\Search\RebuildSearchIndex` | class | Indexes pages for search again: after upgrading, or after a field's settings change what its values say (a choice label, a matrix type's fields). |
| `Actions\Strings\SaveStrings` | class | Edits the strings a page's template declares with @string, in one language. |
| `Actions\Templates\CopySection` | class | Copies a template's section, with its fields and their settings, into other templates. |
| `Actions\Templates\CreateTemplate` | class | Creates a template, optionally copying another template's fields and settings ("from"). |
| `Actions\Templates\DeleteTemplate` | class | Deletes a template that no page uses. |
| `Actions\Templates\UpdateTemplate` | class | Changes a template's label, flags or fields. |
| `Agents\AgentReadTool` | class | A read-only tool a module offers to MCP clients and the AI agent, next to Lynx's own (find_pages, get_page…). |
| `Agents\ReadToolRegistry` | class | Read tools modules add for MCP clients and the AI agent (AgentReadTool). |
| `Api\Concerns\TrashesPages` | trait | The trash part of the pages API. |
| `Api\Concerns\VersionsPages` | trait | Scheduled publishing, edit locks, revisions and drafts in the pages API. |
| `Api\FieldsApi` | class | $lynx-&gt;fields |
| `Api\LanguagesApi` | class | $lynx-&gt;languages: ProcessWire-style API for languages. |
| `Api\PagesApi` | class | $lynx-&gt;pages, modelled on ProcessWire's $pages. |
| `Api\TemplatesApi` | class | $lynx-&gt;templates |
| `Components\ComponentRegistry` | class | App components available to the "component" field type. |
| `Components\RegisteredComponent` | class | An app component that editors and AI agents can place on pages through a "component" field. |
| `Content\PageFiles` | interface | Where the files of CarriesFiles fields live, for content packages. |
| `Doctor\Check` | interface | One thing lynx:doctor looks at. |
| `Doctor\DoctorChecks` | class | The checks lynx:doctor runs: Lynx's own, then those modules add at boot. |
| `Doctor\Finding` | class | One problem a doctor check found: what is wrong and, when there is one, how to fix it. |
| `Doctor\Severity` | enum | How bad a doctor finding is: an error breaks something visitors or editors rely on and makes lynx:doctor fail; a warning is worth a look but the site works. |
| `Events\ContentBatchChanged` | class | Content changed while events were muted (lynx()-&gt;withoutEvents()): one event for every muted ContentChanged, dispatched after the muting ends and the actions commit, so caches, menus and search engines notice. |
| `Events\ContentChanged` | interface | An event after which visitors may see different content: listen to this interface to clear caches, rebuild search indexes or ping a CDN, instead of to every event one by one. |
| `Events\LanguageCreated` | class | Dispatched after the action's transaction commits. |
| `Events\LanguageDeleted` | class | Dispatched after the action's transaction commits. |
| `Events\LanguagePrefixChanged` | class | Dispatched after the action's transaction commits. |
| `Events\LanguageUpdated` | class | Dispatched after the action's transaction commits. |
| `Events\PageDeleted` | class | A page was deleted for good, with every page below it: their ids, dispatched after the action commits. |
| `Events\PageMoved` | class | A page moved to another parent, dispatched after the action commits. |
| `Events\PageReferencesChanged` | class | A save or a template switch added or removed references to other pages, dispatched once per page after the action commits. |
| `Events\PageRestored` | class | A page came back from the trash, under the parent it was restored to. |
| `Events\PageSaved` | class | A page was created ($created) or saved, dispatched after the action commits. |
| `Events\PageSorted` | class | A page moved to another place among its siblings. |
| `Events\PageStatusChanged` | class | A page was published, unpublished, hidden, locked or otherwise flagged. |
| `Events\PageTemplateSwitched` | class | A page switched to another template, with both templates' names. |
| `Events\PageTrashed` | class | A page went to the trash, with every page below it. |
| `Events\SiteStringsSaved` | class | Site texts (strings with scope "site") changed: every page may show them. |
| `Events\TemplateUpdated` | class | A template's settings or fields changed (templates.update), so its pages may show or index their values differently. |
| `Facades\Lynx` | class | The methods of the Lynx service that lynx() returns, such as Lynx::run() and Lynx::hook(), called statically. |
| `Fields\CarriesFiles` | interface | A field type whose values name stored files, such as images. |
| `Fields\ChecksSettings` | interface | A field type whose settings depend on each other, such as a choice's default and its options. |
| `Fields\Choices\Choice` | class | One chosen option of a choice field: its stable value and its label in the current language. |
| `Fields\Choices\ChoiceList` | class | The chosen options of a choice field that allows several, in the order of the field's options. |
| `Fields\DescribesPhpType` | interface | A field type that tells static analysis what templates read: the PHPDoc type of $page-&gt;field for a shared (not translatable) field, as the type presents it, null included when an empty value reads as null. |
| `Fields\FieldRegistry` | class | Read access to field definitions, cached per request. |
| `Fields\FieldTypeRegistry` | class | Registered field types. |
| `Fields\FormatterRegistry` | class | Registered text formatters. |
| `Fields\HasDefaultValue` | interface | A field type whose fields can start with a value: pages.save stores it on a new page, and in a new repeater or matrix item, when the input leaves the field out. |
| `Fields\HasTranslatableTexts` | interface | A field type whose shared value holds texts per language, such as a form question's options with a label in each language. |
| `Fields\HtmlCleaner` | class | Cleans HTML for html textarea fields on every save, whoever sends it (the editor, the API, MCP, the agent, imports): an allowlist of text markup, links limited to safe schemes, and no scripts, event handlers, styles (but a block's text alignment) or embeds. |
| `Fields\Items\HasItemProperties` | interface | An item field type whose items hold fixed values selectors can test, such as the images types: "gallery.tags=hero", "gallery.width&gt;=1200", and inside repeater and matrix items "blocks.gallery.tags=hero". |
| `Fields\Items\ItemFieldType` | class | A field whose value is a list of items, each with values of other fields. |
| `Fields\Items\NestsInItems` | interface | An item field type whose value may sit inside a repeater or matrix item as one JSON value, such as images. |
| `Fields\Items\StoresItems` | interface | A field type whose value is a list of items with their own values, such as a repeater or a matrix. |
| `Fields\Items\Subfield` | class | A field as used inside an item field: the field itself, plus what the item field's settings say about it here (label, description, notes, required, width, and the group it starts). |
| `Fields\Items\SubfieldGroup` | class | Subfields of an item shown together: under a heading with help above and notes (and a link) below, or, for the fields before the first group, with no heading at all. |
| `Fields\MergesInput` | interface | A shared field type whose input may carry values for one language, such as a repeater with translatable subfields: pages.save merges the input into the stored value, so saving one language keeps the others. |
| `Fields\NeedsSavedPage` | interface | A field type whose values can only be made once the page exists, such as images stored in the page's folder. |
| `Fields\NormalizesSettings` | interface | A field type that cleans its settings when a field is created or updated, after the type's settingsRules() passed, such as filling a choice's empty value from its label. |
| `Fields\PresentsValue` | interface | A field type that turns its stored (woken) value into what templates read, such as a list of pages or an image object. |
| `Fields\ProvidesSearchText` | interface | A field type whose values hold words to search. |
| `Fields\ReferencesPages` | interface | A field type whose values point at pages. |
| `Fields\SettingFormats` | class | JSON Schema "format" values that field types put on their settings, so forms and agents know what a string holds beyond its type. |
| `Fields\SettingsPerUse` | interface | A field type whose settings a template, or a block type of a matrix, may set for its own use of the field, such as a page field's "max": one field holds 4 pages in one place and 8 in another. |
| `Fields\Types\BaseFieldType` | class | Defaults for single-value field types stored in one "data" column. |
| `Http\Middleware\WithoutPageVisibility` | class | Switches page visibility rules off for the request: the admin's routes and the MCP web route carry it, so editors and agents see every page. |
| `Languages\LanguageContext` | class | The language of the current request (or of the current closure). |
| `Languages\LanguageNotFound` | class | Thrown when no language has the code, id or prefix asked for. |
| `Languages\LanguageRegistry` | class | Read access to languages, cached per request and flushed after every action. |
| `Livewire\UsesLynxPage` | trait | For app Livewire components placed on Lynx pages. |
| `Lynx` | class | The API root, in the spirit of ProcessWire's API variables: |
| `Models\Field` | class | A field definition. |
| `Models\Language` | class | A content language. |
| `Models\Template` | class | A page template: an ordered list of fields plus flags (has_url, singleton, allowed_templates) and family rules (allow_children, child_templates, parent_templates). |
| `Pages\FieldValue` | interface | The value of a text field as templates see it: &#123;&#123; $page-&gt;body &#125;&#125; prints toHtml(), the value formatted for output; value(), string casts and JSON give the stored value. |
| `Pages\Items\Item` | class | One item of a repeater or matrix as templates read it: $item-&gt;role is the value in the current language with fallback, $item-&gt;partner the pages it references, like page fields. |
| `Pages\Items\ItemList` | class | A repeater's or matrix's items as templates read them: the items switched on, in order; all() includes the ones switched off. |
| `Pages\OldPath` | class | A path a page had before a rename or move. |
| `Pages\Page` | class | A page: a domain object hydrated by PageLoader, not an Eloquent model. |
| `Pages\PageArray` | class | An ordered list of pages, as returned by find() and page-reference fields. |
| `Pages\PageGates` | class | Checks a page passes before the front end renders it. |
| `Pages\PageStatus` | class | Page status flags, stored as a bitmask in lynx_pages.status. |
| `Pages\Publication` | class | Whether visitors may see a page: it and every page above it must be published. |
| `Pages\SharedValue` | class | The value of a shared (not translatable) text field, so it formats in templates like a translatable one. |
| `Pages\TranslatableValue` | class | The value of a translatable field. |
| `Pages\Visibility\PageVisibility` | class | Which pages visitors may see. |
| `Pages\Visibility\VariesByVisitor` | interface | A visibility rule that can tell whether the current visitor sees what a visitor with no cookies and no account sees. |
| `Pages\Visibility\VisibilityRule` | interface | A rule about which pages visitors may see, registered with PageVisibility. |
| `Permissions\Authorizer` | class | Whether an actor holds a permission, optionally for a page or a template. |
| `Permissions\PermissionRegistry` | class | The permissions roles can grant. |
| `Permissions\RoleAssignment` | class | A host app user holding a role. |
| `Query\SelectorException` | class | Thrown for a selector Lynx cannot read; its message says what is wrong. |
| `Schema\SchemaEditing` | class | Who may change the schema. |
| `Schema\SchemaOwners` | class | Which templates and fields belong to Lynx or a module rather than to the site. |
| `Schema\SchemaWording` | class | Where a module keeps translations of the labels it installs. |
| `Search\Plurals\EndingRules` | class | A plural reducer written as word endings: the exceptions first, then the longest ending that matches, and only that one. |
| `Search\Plurals\FrenchPlurals` | class | French plurals: -eaux gives -eau, -aux gives -al, -x after u is dropped, and so is -s. |
| `Search\Plurals\WordReducer` | interface | Turns a word into the form search matches it by: a plural into its singular, or, in languages whose plurals change the end of the word in ways a rule cannot undo (Italian, German), singular and plural into the stem they share. |
| `Search\Plurals\WordReducers` | class | The plural reducers by locale. |
| `Strings\PageStrings` | class | A page's template strings with their code texts and edits, for agents, the admin and content packages. |
| `Strings\StringDefinition` | class | A string a template declares with @string: its key, the text in code (one text, or per language code), and how editors see and visitors get it. |
| `Support\LocalizedText` | class | Schema text editors read, such as a field's label, description or notes: one string for every language, or a map of language codes to text. |
| `Support\Value` | class | Typed reads of loosely typed values (validated input, JSON settings, database rows). |
| `Templates\FieldCondition` | class | A show_if or required_if condition, written like ProcessWire's showIf: "format=onsite\|hybrid, price&gt;0". |
| `Templates\FieldDisplay` | enum | How a template's field shows in the page editor, its "editor" setting. |
| `Templates\TemplateField` | class | A field as used by one template: order, required flag, label override and conditions. |
| `Templates\TemplateRegistry` | class | Read access to templates and their fields, cached per request. |
| `Translation\TranslationProviders` | class | The machine translation providers a site can use, by name. |
| `View\LanguageSwitcher` | class | Links to a page in each language it is published in, for a language switcher. |

## lynxcms/admin

In `LynxCms\Admin`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Admin` | class | Entry points for modules that add admin screens. |
| `AdminAccess` | class | What the current admin user may do, for hiding what they cannot use. |
| `AdminAssets` | class | Stylesheets modules add to every admin page, after the admin's own and before the site's (lynx-admin.stylesheets), so a site's theme still wins. |
| `AdminBranches` | class | Branches of the tree that a module edits on its own screen, like the menus under /_menus/. |
| `AdminServiceProvider` | class | Registers the Livewire admin: config, views, routes, components and the theme stub. |
| `AdminSlots` | class | Livewire components and views modules add to named places in the admin. |
| `Auth\CreateUserWithTemporaryPassword` | class | Creates a user with roles and a temporary password, without sending email. |
| `Auth\InviteUser` | class | Invites someone to the admin: creates the host app user with an unusable password, gives them roles and emails a link to choose a password. |
| `Auth\ResendInvitation` | class | Emails a pending invitation again with a new link; the old link stops working. |
| `Auth\ResetTwoFactor` | class | Turns off a user's two-factor authentication, for someone who lost their phone and their recovery codes. |
| `Auth\SetTemporaryPassword` | class | Replaces a user's password with a temporary one, for someone who forgot theirs on a site that sends no email. |
| `Auth\UserInvited` | class | A superuser invited someone. |
| `Branding\SaveBranding` | class | Saves the admin's branding: the site's name, logo and colours on the sign-in screens and the sidebar, and a logo and colours of their own for dark mode. |
| `EditorLinks` | class | Links modules add to the page editor's header for some pages, such as "Preview form" on form pages. |
| `EditorTabs` | class | Tabs modules add to the page editor, after the template's tabs and before Settings, such as "Cache" on pages the page cache may store. |
| `Editor\BacklinkFilter` | class | Templates whose pages the References tab leaves out. |
| `Editor\Converters\IntegerConverter` | class | Integers: input that is not a number stays a string, so pages.save reports it. |
| `Editor\FieldAddress` | class | The admin address that opens a page's editor at one field: on the field's tab, in the language given, scrolled to the field or to one repeater or matrix item. |
| `Editor\FieldInputs` | class | How the page editor edits each field type: the Blade view of its input, the FormConverter between stored and form values, and optionally a view that displays stored values read only. |
| `Editor\FieldKinds` | class | What each field type holds, in words for the people who set up a site: a name ("Paragraphs"), a group, a Lucide icon and one sentence. |
| `Editor\FieldTypeChoices` | class | The field types the field form offers for a new field. |
| `Editor\FormConverter` | interface | Converts a field type's stored value to what its input holds and back. |
| `Editor\LinkFiles` | class | Files the link dialog of rich text fields offers under "Select file", for the page being edited. |
| `Editor\PreparesDisplay` | interface | A converter that also prepares stored values for the read-only display (change review, fallback previews), loading what the display view needs in one go so the view runs no queries. |
| `Editor\SchemaOwners` | class | Who may change templates and fields in the admin, as core decides it: which module owns a template or field comes from core's registry, and lynx.schema_editing off makes every schema screen read only, as the actions refuse the change anyway. |
| `Editor\TemplateChoices` | class | The templates the New page screen offers (rules for which templates fit under which parent are planned; until then every offered template fits anywhere). |
| `Fields\IconType` | class | An icon name from the admin's Lucide set, such as "calendar". |
| `Home\HomeScreen` | class | What is on the admin's home screen: stats in the row at the top, buttons for common actions beside the welcome, and panels in a main and a side column, Lynx's own lists among them. |
| `Icons\IconSet` | class | The icons the admin can show: the Lucide set's file names ("calendar", "map-pin"). |
| `Livewire\PageEditor` | class | The page editor: every field of the page's template with language tabs and fallback previews. |
| `Livewire\PageStrings` | class | The Texts tab of the page editor, and Setup &gt; Site texts: the strings templates declare with @string, one input per language with the code text as placeholder. |
| `Navigation` | class | Links in the admin's sidebar. |
| `Tree\TreeFilterRegistry` | class | Filters modules add to the page tree's Details view, after Lynx's own. |
| `Tree\TreeMarkers` | class | Small icons modules show beside a page in the tree, such as a key on password-protected pages. |
| `Tree\TreePresenter` | interface | Decides what a page tree row shows. |
| `Users\CreateUser` | class | Creates an account in the host app's users with a password and roles, as lynx:superuser does for a site's first superuser. |
| `Users\LynxUsers` | interface | The host app's users as the Users screen sees them. |

## lynxcms/ai

In `LynxCms\Ai`.

| Name | Kind | What it is |
| --- | --- | --- |
| `AgentRun` | class | The result of $lynx-&gt;ai()-&gt;ask(): the agent's answer and the plan of recorded actions. |
| `LynxAi` | class | lynx()-&gt;ai(): ask the built-in agent to do something in plain language. |

## lynxcms/cache

In `LynxCms\Cache`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\ClearPageCache` | class | Drops stored pages so the next visits render them again: every page, some pages, or the pages of one template; or stored fragments, all or by name. |
| `Actions\InstallPageCache` | class | Creates the page cache settings fields, template and hidden system page. |
| `Actions\SavePageCacheRules` | class | Saves the page cache's template rules and default lifetime on its settings page. |

## lynxcms/finder

In `LynxCms\Finder`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\DeleteSearch` | class | Deletes a saved finder search, for its owner or a superuser. |
| `Actions\SaveSearch` | class | Saves a finder search under a name, or changes a saved one. |

## lynxcms/forms

In `LynxCms\Forms`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\CreateForm` | class | Creates a form in the builder: an unpublished page under the hidden forms page, named by the form's key. |
| `Actions\DeleteSubmission` | class | Deletes a submission and, once that commits, its files. |
| `Actions\DeleteSubmissions` | class | Deletes many submissions for good, with their files once that commits: a list, or every submission a filter matches, a chunk at a time. |
| `Actions\ImportForm` | class | Creates a form from another site's export: forms.create, then its fields and messages with pages.save, unpublished and without recipients, so the owner adds them before publishing. |
| `Actions\InstallForms` | class | Installs the form builder: its fields, templates and the hidden page that holds the forms. |
| `Actions\PurgeSubmissions` | class | Deletes spam and archived submissions older than some days (lynx-forms.purge_after_days), with their files. |
| `Actions\SubmitForm` | class | Receives a form's answers: validates them in the visitor's language, stores the submission with a snapshot of the form and its files on the private forms disk, and announces it after commit, which mails the people who handle it. |
| `Actions\UpdateSubmission` | class | Sets a submission's status (new, read, archived, spam) and the editors' notes on it. |
| `Actions\UpdateSubmissions` | class | Marks many submissions new, read, archived or spam in one go: a list, or every submission a filter matches, a chunk at a time. |
| `Definition\Form` | class | A form defined in code: its fields, optional steps, who hears about submissions and what visitors read. |
| `Definition\FormField` | class | A field of a code form, built fluently: FormField::text('name')-&gt;label(['pt' =&gt; 'Nome', 'en' =&gt; 'Name'])-&gt;required()-&gt;max(120). |
| `Definition\FormStep` | class | One step of a multi-step form: a title and the fields it asks for, in order. |
| `Events\FormSubmitted` | class | A form submission was stored and committed. |
| `FormRegistry` | class | The forms a site has, by key: code forms the app registers, then the builder's published forms. |
| `FormSchema` | class | Templates and fields of the form builder: forms are pages under a hidden "_forms" page, and their fields are blocks of the form_fields matrix, one block type per field kind. |
| `Livewire\FormComponent` | class | A form on a page: &lt;livewire:lynx-form form="quote" :page-id="$page-&gt;id" /&gt;. |
| `Models\Submission` | class | A form submission: the answers, a snapshot of the form's fields when it was sent, the page and language it came from, and how editors handled it. |
| `Models\SubmissionFile` | class | A file sent with a submission, on the private forms disk. |
| `PersonalData` | class | What the site holds about one person from its forms: every submission, in any status and form, where their email address is a whole answer. |
| `Results\FormResults` | class | A form's answers summed up per question, as a survey's results: how many chose each option, the average of a number, the range of a date, the latest written answers. |
| `Rules\FormName` | class | A form's name, which is its key: lowercase letters, digits and single dashes, starting with a letter, as "contact-us". |
| `SubmissionFilter` | class | Which submissions the inbox shows and its bulk actions and export act on: a form, a status ("open" is new and read, "all" every status), text in the answers, a date range, an email address that is a whole answer (for a person's data), and the newest id when the selection was made, so a submission that arrives later is left alone. |
| `Transfer\FormExporter` | class | A builder form's structure as data, for JSON: its name, title, messages and the blocks of its fields, with texts keyed by language code and the code of the site's default language, so another site can key them by its own. |
| `Transfer\FormTransferError` | class | A form that can't be exported: one the site lacks, or one defined in code, which travels with the app's code instead. |

## lynxcms/media

In `LynxCms\Media`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\PruneMedia` | class | Deletes media files nothing refers to any more: not a value of any media field (live pages, drafts, trashed pages) nor of a revision. |
| `Actions\StoreMedia` | class | Stores a file for a page's media field and returns the entry to save in it: from a URL (public servers only, see RemoteFile) or base64, which is how the admin sends uploads. |
| `Values\MediaList` | class | The files of a media field that are switched on, in order. |
| `Values\Pagefile` | class | A file of a page as templates read it: its URL, name, size, description in the current language (with fallback) and tags. |
| `Values\Pageimage` | class | An image of a page. |

## lynxcms/menus

In `LynxCms\Menus`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\CreateMenu` | class | Creates a menu, installing the menu builder first when needed. |
| `Actions\InstallMenus` | class | Creates the menu fields, templates and the hidden system page that holds every menu. |
| `Actions\RebuildMenus` | class | Drops stored menus so the next visit builds them again from their items and the pages they link. |
| `Actions\SaveMenuItem` | class | Creates or updates a menu item: a section label, a page (optionally with its child pages) or a custom link. |
| `Menus` | class | lynx()-&gt;menus(): create menus and items, and read a menu resolved for the current language. |
| `Reading\Menu` | class | A menu resolved for the current language: what a Blade view or a JSON API needs. |
| `Reading\MenuItem` | class | One resolved menu entry in the current language. |

## lynxcms/page-password

In `LynxCms\PagePassword`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\DisablePagePassword` | class | Takes the password field off a template. |
| `Actions\EnablePagePassword` | class | Lets a template's pages have a password: adds the page_password field to the Content tab's sidebar, installing first. |
| `Actions\InstallPagePassword` | class | Creates the page_password field. |
| `PagePasswordSchema` | class | The field the pagePassword module adds to templates that ask for it. |

## lynxcms/seo

In `LynxCms\Seo`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\DisableSeo` | class | Turns SEO off for a template. |
| `Actions\EnableSeo` | class | Turns SEO on for a template: its pages get their own title, description, share image, noindex and canonical fields. |
| `Actions\InstallSeo` | class | Creates the SEO fields, the settings template and its hidden system page. |
| `Actions\MapTemplate` | class | Maps a template to a schema.org type for structured data, as the admin's Structured data tab does: each property names its source (a field, @title, @url, @published, @modified, |
| `Actions\UnmapTemplate` | class | Removes a template's structured data mapping: its pages keep the site, page and breadcrumb nodes every page has. |
| `JsonLd\JsonLd` | class | A page's JSON-LD graph: the site (WebSite, its Organization or Person), the page (WebPage, BreadcrumbList), the entity its template is mapped to, and what extensions add. |
| `JsonLd\JsonLdGraph` | class | The nodes of one page's JSON-LD @graph. |
| `RobotsTxt` | class | The /robots.txt body. |
| `SeoData` | class | Everything a page's &lt;head&gt; says to search engines and social networks, in one language. |
| `SeoSchema` | class | Names of the fields and the template the SEO module installs. |

## lynxcms/sitemap

In `LynxCms\Sitemap`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\ClearSitemapCache` | class | Drops the built sitemap, so the next request builds it again. |
| `Actions\InstallSitemap` | class | Creates the sitemap settings fields, template and hidden system page. |
| `Sitemap` | class | The sitemap's files, built once and cached: /sitemap.xml (one urlset, or an index of parts) and each part. |
| `SitemapFilters` | class | Rules that keep a page out of the sitemap in a language, on top of status and templates. |
| `SitemapSettings` | class | The sitemap's settings in effect: what editors set on the settings page, else the config. |

## lynxcms/static-site

In `LynxCms\StaticSite`.

| Name | Kind | What it is |
| --- | --- | --- |
| `Actions\ExportStaticSite` | class | Exports the site as static files to lynx-static.path, to host without PHP. |
| `StaticSiteBuilt` | class | A static export finished building in its staging folder. |
