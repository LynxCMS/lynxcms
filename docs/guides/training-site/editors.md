---
title: The Editors' Side
description: Chapter 4 of the training site guide. Page editor layouts, roles for the school's staff, drafts, scheduled publishing and translation.
---

# The Editors' Side

Atlas Training's staff are not developers. A course manager adds next season's dates, a writer posts news, and the owner checks it all. This chapter shapes the admin around those jobs, and makes sure each person can only change what is theirs.

## Editor layouts that match the work

By default the page editor lists a template's fields in order. Arrange them so the form reads like the job: the course's facts in a sidebar, its teaching on a tab of its own, notes that say what a field is for:

<<< @/snippets/training/database/seeders/AtlasEditorsSeeder.php#layouts{php}

The layout only changes the editor. Values, selectors and schema files stay the same, and staff can rearrange it later under **Settings › Templates** by dragging fields between tabs and columns. See [Arranging the page editor](/basics/templates-and-fields#arranging-the-page-editor).

## Roles for the staff

`lynx:install` made a `superuser` role for the owner and an `editor` role that can work on every page. Atlas wants narrower ones: a course manager who runs the catalogue and nothing else, and a news writer:

<<< @/snippets/training/database/seeders/AtlasEditorsSeeder.php#roles{php}

A grant can be limited to **branches** of the tree (a page and everything under it) and to **templates**. The course manager may create pages under `/courses/`, but only courses and sessions; editing the About page or writing news is refused, in the admin, through the API and for an AI agent acting for them alike. The admin's page tree shows each person only the pages they may work on.

Give staff their roles under **Access › Users**, where superusers also invite new staff by email. Roles are schema: `lynx:schema:export` writes them with the branches as paths, so they move between environments.

## Drafts and previews

A news writer can prepare an update without changing the live page. In the editor that is **Save as draft**; in code:

```php
lynx()->pages->saveDraft('/posts/new-porto-studio/', ['summary' => ['en' => 'Classes start on Monday.']]);
lynx()->pages->publishDraft('/posts/new-porto-studio/');
```

Visitors keep seeing the published page. Signed-in staff open the draft at the page's own URL with `?preview=draft`, in the real design, so they review what visitors will see. Your templates need nothing for it.

## Publishing on a schedule

A post about the summer intensive can go live at nine on Monday while everyone is busy:

```php
lynx()->pages->schedule('/posts/summer-intensive/', '*', publishAt: now()->next('Monday')->setTime(9, 0));
```

In the editor, the page's **Settings** tab has the same dates, per language. Laravel's scheduler runs `lynx:schedule:run` every minute, so the server needs the [scheduler's cron entry](/getting-started/installation#the-scheduler).

## Translation

Every text field has a tab per language. Pages fall back to English for anything not translated yet, and `@field` marks those values with `lang="en"`, so a Portuguese page with an English paragraph is still correct HTML.

With `lynxcms/ai` installed, `pages.translate` fills a page's missing Portuguese values by machine and marks them for review; it never overwrites a human translation. See [MCP Server and Agent](/ai/#translation).

## What you get

- **The client:** an admin shaped like their work, staff who can't break what isn't theirs, drafts to review, and posts that publish themselves.
- **You:** permissions checked by Lynx's action runner for every way in (the admin, your code, MCP, the agent), written once as roles, and kept in Git with the schema.
