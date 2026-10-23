---
title: AI
description: The MCP server and the built-in agent, both generated from Lynx's actions.
order: 7
---

# AI

The MCP server and the agent expose the same actions as the PHP API, with the same validation, permissions and activity log. Actions that should never be tools (such as `users.invite` or `content.import`) are left out.

## MCP server

`lynxcms/mcp` registers an MCP server called `lynx`.

```bash
php artisan mcp:start lynx
```

Point Claude Desktop, Claude Code or any MCP client at that command. The server starts with `describe_schema`, `find_pages`, `get_page` and `get_page_history`, plus one tool per action (`pages_save`, `pages_move`, `languages_create`…). Modules add read tools too, such as `list_submissions` and `get_submission` from the forms module. The built-in agent gets the same tools.

A module's read tool extends `LynxCms\Core\Agents\AgentReadTool` (name, description, schema, the permission it needs, and `handle()`), and is registered with `app(ReadToolRegistry::class)->register(MyTool::class)`. It runs as the client's user and checks that permission first. Writes stay actions.

To serve it over HTTP, set `LYNX_MCP_WEB=true`. It listens at `/mcp/lynx` behind `auth:sanctum` (see `lynx.mcp.web`).

Agents are told to dry-run every write first and show you the changes. Destructive tools are marked as such.

## The built-in agent

`lynxcms/ai` adds an agent built on `laravel/ai`.

```bash
php artisan lynx:ask "Create a new course with this content and translate it to PT" --file=course.md
php artisan lynx:ask "Create a template called consulting, copy the course fields and add client and duration" --file=spec.md
```

The agent works in plan mode: it dry-runs its changes and you approve the plan before anything is written. `--apply` skips the question.

| Setting | Meaning |
| --- | --- |
| `LYNX_AI_PROVIDER`, `LYNX_AI_MODEL` | The `laravel/ai` provider and model. |
| `LYNX_AI_TRANSLATION_MODEL` | Model used by `pages.translate`. |
| `lynx.agents.allowed_languages` | Languages agents may write; empty means all. |
| `LYNX_AGENT_DEFAULT_LIMIT`, `LYNX_AGENT_MAX_LIMIT` | Pages `find_pages` returns without a limit (25) and at most (100). |

## Translation

`pages.translate` fills a page's missing values in another language and marks them as machine translations for review. It never overwrites human translations unless asked.

## Laravel Boost

With [Laravel Boost](https://github.com/laravel/boost) installed, `php artisan boost:install` picks up Lynx's guideline and skills (templates, content model, actions, admin screens, menus), so coding agents in your app know how to work with Lynx.
