---
title: Publishing
description: Status, drafts, revisions, scheduling, the trash, old URLs and edit locks.
order: 5
---

# Publishing

Every operation here is an action: it can run as a dry run, is checked against permissions and is logged.

## Status

```php
lynx()->pages->setStatus($page, add: ['hidden']);
lynx()->pages->setStatus($page, remove: ['unpublished']); // publishes the page
```

| Status | Effect |
| --- | --- |
| `unpublished` | The page and every page below it return 404 and leave listings. |
| `hidden` | Reachable by URL, left out of listings. Children are not affected. |
| `locked` | Cannot be saved, moved, sorted, translated, switched or deleted until unlocked. |
| `unique` | Its default-language name is used by no other page anywhere. |
| `system` | Needed by Lynx or a module: cannot be deleted, moved or switched. Values can be edited. |

A page is published in a language once it has a name in that language.

## Drafts

A page can have one draft, shared by every editor. It holds changes that are not live yet: field values and the page's own texts (`strings.save` with `draft: true`).

```php
lynx()->pages->saveDraft($page, ['title' => ['pt' => 'Novo título']]);
lynx()->pages->publishDraft($page);
```

Editors preview the draft at the page's URL with `?preview=draft`. `pages.discard_draft` drops it.

## Revisions

Every save that changes values or the page's texts keeps a revision; the newest 50 per page stay (`LYNX_REVISIONS_KEEP`). The editor's **History** lists them, and `pages.restore_revision` puts one back as a new save, so a restore can be undone too.

When saving through the API or tools, pass the revision you loaded as `expected_revision`: a save made by someone else in between is refused instead of overwritten.

## Scheduling

```php
lynx()->pages->schedule($page, 'pt', publishAt: now()->addDay());
lynx()->pages->schedule($page, 'pt', unpublishAt: now()->addMonth());
lynx()->pages->schedule($page, '*', publishAt: now()->addDay()); // every language
```

With `'*'` every language is checked first, so a time one language refuses changes nothing. In the editor's Settings tab, one row sets every language; tick "Different times per language" to set each one. The editor opens on the per language rows by itself when the saved times differ.

`lynx:schedule:run` applies due entries every minute from Laravel's scheduler.

## The trash

```php
lynx()->pages->trash('/courses/old/');
lynx()->pages->restore('/courses/old/');
lynx()->pages->emptyTrash();
```

Trashed pages and their children leave the site and can be restored to their place (or another parent) until the trash is emptied. Prefer the trash to `delete()`, which removes pages for good.

## Old URLs

Renaming or moving a page keeps the old paths as redirects, per language. `redirects.delete` removes one; the current URL cannot be removed.

## Edit locks

When someone opens a page in the admin editor, they hold a lock for two minutes (`LYNX_EDIT_LOCK_SECONDS`), renewed while the page stays open. Writes to the page from anyone else are refused until it is free. Superusers can take a lock over.
