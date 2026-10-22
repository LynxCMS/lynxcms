---
title: Lynx
description: A multilingual CMS for Laravel that people and AI agents can build and run safely.
order: 1
---

# Lynx

Lynx brings ProcessWire's page tree, runtime fields and selector API into any Laravel 13 app, with languages as a core concept.

- **Languages are rows, not columns.** Add a language to a running site with one insert. No migration, no deploy.
- **Fallback at read time, field by field.** A partly translated page shows each field in its own language or in the next language of its fallback chain (`pt-BR`, then `pt`, then the default), and templates know which is which.
- **Translated URLs with history.** `/courses/delivery-8` and `/pt/cursos/navegando-entrega-8`. Renamed or moved pages keep redirecting from their old URLs.
- **Every write is an action.** The PHP API, Artisan, the admin, the MCP server and the built-in agent call the same validated actions, with hooks, dry runs, permissions and an activity log.
- **AI native.** An MCP server and a `laravel/ai` agent are generated from the action registry. The agent plans first and applies only what you approve.

> [!NOTE]
> Lynx is pre-release. APIs may still change before 1.0.

## Packages

| Package | What it adds |
| --- | --- |
| `lynxcms/core` | Languages, fields, templates, pages, selectors, actions, routing and Blade helpers. |
| `lynxcms/admin` | The Livewire admin: page tree, editor, schema screens, languages, roles, users and sign-in. |
| `lynxcms/menus` | Menus as pages, the menu editor and `<x-lynx-menu>`. |
| `lynxcms/mcp` | An MCP server generated from the actions. |
| `lynxcms/ai` | The built-in agent and the translation provider. |

Start with [Installation](/docs/getting-started/installation).
