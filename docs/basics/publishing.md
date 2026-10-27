---
title: Publishing
description: Page statuses, drafts, revisions, scheduled publishing, the trash, old URLs and edit locks.
---

# Publishing

Editors rarely change a live page in one step. Lynx gives them statuses that take a page offline or out of lists, drafts that wait for review, revisions to compare and restore, publishing on a schedule, and a trash to recover from mistakes.

Each of these is an [action](/getting-started/concepts#actions), so it checks the user's permissions, can run as a dry run, and shows up in the activity log, whether an editor clicks a button or your code calls it.

## Page status

A page's status flags change how visitors and editors see it:

| Status | Effect |
| --- | --- |
| `unpublished` | The page and every page below it return 404 and leave lists. |
| `hidden` | Reachable by its URL, but left out of lists, menus and searches. Pages below it are not affected. |
| `locked` | Can't be saved, moved, sorted, translated, switched to another template or deleted until it is unlocked. |
| `unique` | No other page anywhere may use its default language URL name. |
| `system` | Needed by Lynx or a module: it can't be deleted, moved or switched to another template, but its values can be edited. |

Editors set them in the page's **Settings** tab. In code, add and remove flags:

```php
lynx()->pages->setStatus('/news/launch', add: ['hidden']);
lynx()->pages->setStatus('/news/launch', remove: ['unpublished']); // publishes the page
```

### Publishing in a language

A page is published in a language once it has a URL name in that language. Editors give it one in the page's **Settings** tab, where they can also take a page offline in a single language. Until a page has a name in a language, links to it in that language point to its default language URL. See [Languages](/basics/languages#urls).

## Drafts

A draft holds changes that are not live yet, so an editor can prepare an update and publish it later, or have someone review it. A page has at most one draft, shared by every editor, holding field values and the page's own [editable texts](/basics/views#editable-template-wide-or-site-wide-texts).

In the page editor, editors choose to save their changes as a draft, and publish it when it is ready. In code:

```php
lynx()->pages->saveDraft('/about', ['title' => ['en' => 'About us', 'pt' => 'Sobre nós']]);
lynx()->pages->publishDraft('/about');
lynx()->pages->discardDraft('/about');
```

Signed-in editors preview a draft at the page's URL with `?preview=draft`. Previews then stay on for the rest of their session, so links and the language switcher keep showing drafts, until `?preview=live` turns them off. A bar at the bottom of the page says which version they are looking at, and previews are never cached or shown to search engines. Your templates need no changes for any of it.

## Revisions

Every save that changes a page's values or texts keeps a **revision**, and the newest 50 per page are kept (`LYNX_REVISIONS_KEEP`). The page editor's **History** lists them, compares one with the current page, and restores it. Restoring is a new save, so it can be undone too.

When two people edit the same page, the second save could overwrite the first. The admin prevents that with [edit locks](#edit-locks). When you save through the API or MCP tools, pass the revision you loaded as `expected_revision`: a save that someone else made in between makes yours fail instead of being overwritten.

## Scheduled publishing

Pages can publish or unpublish themselves at a set time, in one language or in all of them:

```php
lynx()->pages->schedule('/news/launch', 'en', publishAt: now()->addDay());
lynx()->pages->schedule('/news/launch', 'en', unpublishAt: now()->addMonth());
lynx()->pages->schedule('/news/launch', '*', publishAt: now()->addDay()); // every language
```

With `'*'`, Lynx checks every language first, so a time that one language can't take changes nothing. In the page's **Settings** tab, one row sets every language; editors tick "Different times per language" to set each one.

The command `lynx:schedule:run` applies the times that are due. Laravel's scheduler runs it every minute, so the server needs the [scheduler's cron entry](/getting-started/installation#the-scheduler). Each page it changes gets an entry in that page's activity, saying what happened and in which language.

## The trash

In the page tree, editors move a page, with every page below it, to the **trash**. Trashed pages leave the site, and editors can restore them to their place, or under another parent, or delete them for good from the trash.

```php
lynx()->pages->trash('/news/old');
lynx()->pages->restore('/news/old');
lynx()->pages->emptyTrash();
```

`lynx()->pages->delete()` removes a page and its children for good, without the trash. Prefer the trash, which editors can undo.

## Old URLs

When an editor renames or moves a page, its old URLs keep working: they redirect to the new address, in every language. Editors can remove an old URL in the page's **Settings** tab, and the `redirects.delete` action does the same in code. A page's current URL can't be removed.

## Edit locks

When an editor opens a page in the admin, they hold a lock on it for two minutes (`LYNX_EDIT_LOCK_SECONDS`), renewed while the page stays open. Anyone else can view the page, but their changes are refused until it is free, so two editors never overwrite each other. Superusers can take a lock over.
