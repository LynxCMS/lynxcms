---
title: Core concepts
description: Pages, templates, fields, languages and actions.
order: 3
---

# Core concepts

## Pages in a tree

Everything is a page: content, settings, menus and the trash. Pages form one tree under the root page `/`. Each page has a name per language, which builds its URL, and a status (hidden, unpublished, locked, unique, system).

## Templates and fields

A page uses one template. A template is an ordered list of fields, with rules for where its pages may go (`allow_children`, `child_templates`, `parent_templates`). Fields are created once and shared by templates. Lynx creates their tables at runtime, so you never write migrations for them.

## Languages

Languages are rows. There is always a default language, and every other language falls back along a chain that ends at the default. Translatable fields store one value per language and fall back when a value is missing. Fallback happens when reading: Lynx never copies values between languages.

A page is published in a language once it has a name in that language.

## Actions

Every write is an action: `pages.save`, `pages.move`, `languages.create` and so on. The runner validates the input, checks permissions, wraps the work in a transaction, runs hooks, records the changes and logs the activity. Dry runs roll everything back and return the changes. The admin, Artisan, the MCP server and the agent all use the same actions. See [Actions and hooks](/docs/reference/actions-and-hooks).

## Data is kept

- Removing a field from a template keeps its values as orphans. Only `pages.purge_orphans` deletes them.
- Renaming or moving a page keeps the old paths as redirects.
- Trashed pages can be restored until the trash is emptied.
