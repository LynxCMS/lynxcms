# Security review of the forms package, 2026-11-25

Reviewed on 2026-11-25, on `main` at `8a1439d8`. The review was read-only: no code changed and no test ran. Every finding was traced in the code, and the file and line are given so each can be checked. Two facts about dependencies were read in `vendor/`: how Livewire 4.4.9 starts an upload, and how Laravel escapes the lines of a notification mail.

## Scope

Covered: all of `packages/forms` (`src`, `config`, `database`, `resources/views`), about 6,000 lines without the language files. The questions were: what an anonymous visitor can send and where it ends up (the database, the disk, mail, the admin, CSV), whether the spam guards can be skipped, who may read and delete submissions, and whether the form builder and import can do more than build forms.

Not covered: Spatie's honeypot package, Livewire's own upload endpoint beyond how it is started, the public stylesheet, the language files, and testing against a running server. The earlier report (`security-review-2026-11.md`) added `nosniff` to downloads of files sent with forms.

## Summary

No SQL injection, stored XSS, mail injection or CSV injection was found. Nothing here rates above Low. The package treats a visitor's answers as data in every place they are shown or sent.

| Id | Severity | Finding | Where |
|---|---|---|---|
| L1 | Low | Every public form lets an anonymous visitor store files in Livewire's temporary folder, file field or not | `Livewire/FormComponent.php:42` |
| L2 | Low | A file's type is checked by its name only | `Definition/FormValidator.php:153` |
| L3 | Low | The submission limit counts per IP address as Laravel sees it, and only on sending | `Livewire/Concerns/GuardsSubmissions.php:82` |
| L4 | Low | The auto-reply mails any address a visitor types | `Listeners/NotifyRecipients.php:61` |
| L5 | `forms.view` reads submissions and marks them read or new. Marking them archived or spam, which the purge deletes later, and writing notes need `forms.manage`, in `UpdateSubmission`, `UpdateSubmissions` and the admin, which shows those controls only to `forms.manage`. `Submission::PURGED_STATUSES` names the statuses the purge deletes. |

## Findings

### L1. Temporary uploads on every form

`FormComponent` uses Livewire's `WithFileUploads` trait for every form (`Livewire/FormComponent.php:42`). The trait's `_startUpload()` hands a signed upload address to whoever calls it, for any property name, without asking whether the form has a file field (`vendor/livewire/livewire/src/Features/SupportFileUploads/WithFileUploads.php:13`). Livewire then accepts files with its own rules, which default to `required`, `file` and `max:12288`: any type, up to 12 MB each, 60 requests a minute per address.

So a contact form with three text fields gives an anonymous visitor a way to write files to the server's `livewire-tmp` folder. The form's own rules (extensions, size, count) apply later, when the form is sent, and never to what sits in the temporary folder. Livewire deletes temporary files after 24 hours.

**Who:** any visitor of a page with a form.

**Effect:** disk space. At the default limits one address can store about 700 MB a minute.

**Fix:** use the trait only for forms that have a file field (a second component class, chosen by `FormTag`), and say in the docs to set `livewire.temporary_file_upload.rules` and `middleware` to what the site's forms need. The upload rules could also be set from the largest file field the site's forms have.

### L2. File types are checked by name

A file field's rule is `extensions:pdf,doc,...` (`Definition/FormValidator.php:153`). Laravel's `extensions` rule reads the name the browser sent. The content is not compared: the MIME type found in the content is stored (`Actions/SubmitForm.php:163`) but never checked against the extension. A program renamed to `invoice.pdf` passes. A form builder can also allow every type with `*` (`Definition/FormField.php:59`).

The download side limits the harm. Files live on a private disk under random names, and `DownloadSubmissionFile` sends them as attachments with `nosniff`, so nothing runs in the admin's origin. What remains is a staff member opening a file from a stranger on their own computer.

**Fix:** add Laravel's `mimes` rule beside `extensions`, so name and content must agree. In the submission screen, show the stored MIME type when it does not match the extension.

### L3. What the submission limit counts

The limit is five submissions per form in ten minutes, keyed on a hash of `request()->ip()` (`Livewire/Concerns/GuardsSubmissions.php:82`). Two things follow:

- **Behind a proxy.** Without trusted proxies configured, every visitor has the proxy's address, and five submissions from anyone close the form for everyone for ten minutes. With every proxy trusted, the `X-Forwarded-For` header is the visitor's to write, and the limit stops nobody. This is the app's configuration, but the forms docs are where a site owner would look for it.
- **Only `submit()` counts.** `updated()` and `next()` validate on every call without a limit (`Livewire/FormComponent.php:67`, `:84`). They write nothing and cost little.

Turnstile, when on, covers what the limit does not.

**Fix:** a sentence in the docs about `trustProxies`, and a Doctor check that warns when the app sits behind a proxy it does not trust.

### L4. The auto-reply writes to any address

A form with an auto-reply mails the address typed into its reply-to field (`Listeners/NotifyRecipients.php:61`). The address belongs to whoever the visitor says. The mail holds only the form's title and the text staff wrote, never the visitor's answers, so it cannot carry a message of the visitor's choosing. It can still send unwanted mail from the site's domain to a stranger, five times in ten minutes per address and form, or far more through L3.

This is how confirmation mails work everywhere. It is listed so the choice is known: turning Turnstile on is the fix for a site that sees abuse.

### L5. `forms.view` can lead to deletion

`forms.view` is described as "See and sort form submissions", and deleting needs `forms.manage`. `UpdateSubmission` and `UpdateSubmissions` need only `forms.view` (`Actions/UpdateSubmission.php:35`, `Actions/UpdateSubmissions.php:37`), and with a filter they change every submission at once. `PurgeSubmissions` deletes spam and archived submissions older than `purge_after_days`, 180 by default (`Actions/PurgeSubmissions.php:64`). So a role that may not delete can mark everything as spam, and the scheduler deletes it for them once it is old enough. The same role can overwrite the team's notes.

**Fix:** either say so in the permission's description, or have the purge skip submissions marked by a role without `forms.manage`. An activity entry already records who marked what.

## Observations

- **PostgreSQL and references that are not UUIDs.** The `uuid` column is a real `uuid` type on PostgreSQL. `ResolvesSubmissions` and `SelectsSubmissions` compare it with whatever was given, including a plain id (`where('uuid', '5')`, `Actions/ResolvesSubmissions.php:22`), and the admin route `forms/{submission}` takes any text. PostgreSQL refuses text that is not a UUID with an error, where SQLite finds nothing. This was not run. If it holds, the "uuid or id" reference the actions document fails on PostgreSQL, and a mistyped address in the admin gives a 500 instead of a 404. Check the reference with `Str::isUuid()` before the query.
- **Dates from the query string.** `SubmissionFilter::fromArray()` passes `from` and `to` to `CarbonImmutable::parse()` (`SubmissionFilter.php:42`). The actions validate them first, but `ExportSubmissions` feeds it the request's query as it comes (`Admin/Http/ExportSubmissions.php:35`), so `?from=x` is a 500. Staff only.
- **Export of an unknown form.** `ExportSubmissions::columns()` reads `->fields` on a query's first row without checking that there is one (`:68`). A form key with no form and no submissions is a 500.
- **Date answers.** The `date` rule accepts anything `strtotime` reads, such as `tomorrow`, and the answer is stored as typed. Results compare dates as text, so such answers sort wrongly. `date_format:Y-m-d` would match what a date input sends.
- **Search wildcards.** The inbox search does not escape `%` and `_` (`SubmissionFilter.php:130`), while the email filter beside it does. Staff only, and the worst result is a broader match.
- **One permission for all forms.** `forms.view` covers every form's submissions, in the inbox, the exports, the agent tools and the notification mails. A site with a job application form and a newsletter form cannot give a role one without the other.

## Checked and found sound

- **Answers** (`Definition/FormValidator.php`). Every field type has a type rule and a length limit. Choices must be one of the form's options. Only fields the form defines and currently shows are stored, and rows of a group keep only the group's own fields. Extra keys in the request are dropped.
- **Component state** (`Livewire/FormComponent.php`, `core/Livewire/UsesLynxPage.php`). The form key, the page id and the language are locked. `step` and `isSent` are open, but `send()` validates every field again, so skipping steps gains nothing. A form that is unpublished or deleted answers 404, also for a visitor who still holds the page.
- **Mail** (`Notifications/*`). Laravel renders notification lines through an encoder that escapes HTML, and its Markdown converter escapes raw HTML and refuses unsafe links. `PlainMarkdown::escape()` covers the Markdown characters, including `|` for tables, so an answer cannot become a link, a heading or an image. The reply-to address passes Laravel's `email` rule, which leaves no room for header lines.
- **Admin screens.** Every view escapes answers, file names and the user agent. The one raw output with data in it (`results.blade.php:37`) escapes the numbers it inserts. Each Livewire component authorizes its screen on every request, and delete buttons check `forms.manage` again.
- **CSV.** `ExportSubmissions` passes every answer and the account through `SpreadsheetCell::safe()`, so a cell cannot start a formula.
- **Files.** Stored as `forms/{uuid}/{field}-{random}.{extension}` on the private disk. The field name comes from the form's definition, never from the request. A download checks `forms.view` and that the file belongs to the submission in the address.
- **Form keys in selectors** (`Builder/PageForms.php:25`). A key must match `^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$` (with the `D` modifier) before it goes into a selector, so admin routes and agent tools cannot add clauses.
- **SQL.** Query builder everywhere. The three raw fragments are constants with bound values.
- **Turnstile.** The secret is stored encrypted and only a superuser can save it. A token is checked once, and an unreachable Cloudflare refuses the submission.
- **Once per user.** A lock keeps two answers sent at the same moment from both being stored, and guests are refused.
- **Import.** `forms.import` accepts only the block types and values the form builder has, creates the form unpublished, and never takes recipients from the file.
- **Privacy.** No IP address is stored. The limit keeps a hash in the cache only.

## Suggested order

1. L1: split the component, or at least document Livewire's upload settings.
2. L2: one rule added to the validator.
3. The PostgreSQL observation, after confirming it with a test.
4. L5 and L3 as wording in the docs and the permission's description, unless the purge is changed.
5. The remaining observations.

## Resolution

Fixed on 2026-11-26 on the branch `fix/forms-security-review`, each change with a test that fails without it.

| Id | Fix |
|---|---|
| L1 | `GuardsUploads` wraps Livewire's `_startUpload()`: a form hands out an upload address only for its own file questions (403 otherwise), and only for files whose extension, size and count the question takes, as the browser describes them. The forms page points to `livewire.temporary_file_upload.rules` for Livewire's own limit. |
| L2 | `ContentMatchesExtension` checks that the type read from a file's bytes fits its extension, allowing formats built on another (Office files as zip or OLE storage, CSV as plain text) and content the reader can't name. A question that takes any file is unchanged. |
| L3 | The docs explain trusted proxies next to the submission limit. The Doctor check was left out: Doctor runs on the command line, where there is no request to tell a proxy by. |
| L4 | The docs say an auto-reply mails whatever address a visitor types, and that Turnstile is the remedy for abuse. |
| L5 | `forms.view`'s description, in every language, and the permissions table say that sorting as spam or archived leads to the purge. The purge is unchanged. |

Observations:

| Observation | Fix |
|---|---|
| PostgreSQL and references that are not uuids | Confirmed on PostgreSQL. Uuids and ids are compared apart in `ResolvesSubmissions`, `SelectsSubmissions` and `get_submission`, and the admin routes match only uuids, so other addresses answer 404. |
| Dates from the query string | Only Y-m-d days count; anything else is ignored. |
| Export of an unknown form | 404. |
| Date answers | `date_format:Y-m-d`. |
| Search wildcards | Escaped, as in the email filter. |
| One permission for all forms | Not changed: a design question for a site that needs it. |
