# Plan: roles and permissions

Status: built (steps 1 to 6). Decisions taken with the product owner on 2026-10-07 are marked **Decided**.

## Why

Today one gate (`accessLynxAdmin`) lets any signed in user do everything in the admin, and MCP clients, the AI agent and the pages API are not limited at all. Sites with several editors need "blog editors only edit the blog", "only publishers publish", and agents that cannot do more than the person behind them. ADR-0027 also left a gap: removing a module's system pages needs a privileged path.

## Decisions

| Topic | Decision |
| --- | --- |
| Where roles live | **Decided:** Lynx owns roles and permissions in its own system tables, edited in the admin and exported with `lynx:schema:export`. Host app users are mapped to roles; Lynx does not own users. |
| Granularity | **Decided:** a role grants permissions, each optionally limited to templates and to branches of the page tree. |
| MCP and AI | **Decided:** an MCP token or an agent prompt acts with the permissions of the user it belongs to. |
| Where it is enforced | Proposed: in core, by the `ActionRunner` and inside actions, so the admin, the API, MCP, the agent and imports follow the same rules (as ADR-0009 and ADR-0027 already do for writes and locks). |
| Reads | Proposed: front end reads stay public. The admin and the read tools for agents (`find_pages`, `get_page`) check `admin.access` and, for drafts, the page permissions. |
| Schema export | **Decided:** `lynx:schema:export` and `apply` include roles and their grants, never user assignments, which stay per environment. |
| Publishing | **Decided:** `pages.publish` is separate from `pages.edit` from the first version, so editors can prepare unpublished pages that only publishers release. |
| Field level permissions | **Decided:** left out of the first version. Grants stop at action, template and branch; a field scope can be added to grants later without changing the model. |

## Model

```
lynx_roles             id, name (slug), label, is_superuser, timestamps
lynx_role_permissions  id, role_id, permission, templates (json|null), branches (json|null)
lynx_role_user         role_id, user_id (string, any key type), assigned_by, timestamps
```

`templates` holds template names (exported by name, not id). `branches` holds page ids whose whole subtree is covered; null means "everywhere". A user's rights are the union of their roles' grants. A role with `is_superuser` passes every check. These are system tables, so Eloquent models are allowed (AGENTS.md rule 8).

### Permissions

A fixed list owned by core, extendable by modules through a registry (`lynx()->permissions()->register('menus.manage', 'Manage menus')`):

| Permission | Covers | Scoped by template and branch |
| --- | --- | --- |
| `admin.access` | Opening the admin at all (replaces the default rule of the `accessLynxAdmin` gate) | No |
| `pages.view` | Seeing a page's editor and unpublished content in the admin and agent read tools | Yes |
| `pages.edit` | `pages.save` on existing pages, `pages.translate`, `redirects.delete` | Yes |
| `pages.create` | `pages.save` for new pages, checked against the parent and the new page's template | Yes |
| `pages.publish` | Adding or removing `unpublished` (both in `pages.save` and `pages.status`) | Yes |
| `pages.status` | Hidden and locked flags | Yes |
| `pages.move` | `pages.move` (checked at source and destination) and `pages.sort` | Yes |
| `pages.delete` | `pages.delete`, checked for every page in the branch | Yes |
| `pages.template` | `pages.switch_template` | Yes |
| `schema.manage` | Fields, templates, languages, `pages.purge_orphans` | No |
| `agent.use` | Using the built-in agent and approving its plans | No |
| `menus.manage` | Menu actions (registered by the menus module); a role granting it must grant `pages.view` too | No |
| `system.manage` | The privileged path for system pages (module uninstall), see below | No |

Install creates two roles: `superuser` (assigned to the first user by `lynx:install --user=ID`) and `editor` (admin access, view, edit, create, status, move, publish on every template and branch).

## Enforcement

1. **Declaring.** Each action declares the permission it needs: a new static `permission(): ?string` on the action contract. `null` means "superuser only", so a new action is closed by default.
2. **Coarse check in the runner.** Before `handle()`, `ActionRunner` asks `Authorizer::allows($actor, $permission)`: does the actor hold the permission anywhere at all? This fails fast and covers schema actions completely.
3. **Scoped check inside actions.** Page actions call `$context->authorize('pages.edit', $page)` after resolving their page, the same way they now call `PageGuard`. The check walks the grants: template name in the grant's list (or no list) and page inside one of the grant's branches (one closure table query, cached per request).
4. **Actors.**

| Actor | Rights |
| --- | --- |
| `user` | Their roles |
| `mcp`, `ai` with a user id | That user's roles (**Decided**) |
| `mcp`, `ai` without a user id | Refused, unless `lynx.permissions.anonymous_role` names a role |
| `cli` | Everything (whoever runs Artisan already owns the server) |
| `system` | Everything; used by installers and by `lynx()->asSystem(fn () => …)` for module uninstall |

5. **System pages.** `PageGuard` lets the `system` actor through when the actor also holds `system.manage` or is the internal system actor. This closes the uninstall gap from ADR-0027 without weakening it for editors.
6. **Gate integration.** Lynx registers its permissions as Laravel abilities (`Gate::allows('lynx.pages.edit', $page)`, `@can` in Blade), and the default `accessLynxAdmin` rule becomes "holds `admin.access`". Host apps can still redefine the gate.
7. **Errors.** A refusal is an `AuthorizationException` with a message naming the permission and, for scoped checks, the template or branch, so MCP clients and the agent can explain it.

## Admin

* **Roles screen** (`/lynx/roles`): list, create, edit a role with a grid of permissions; per permission, optional template multi select and branch picker (the page tree in a picker mode).
* **Users screen** (`/lynx/users`): lists host app users through a small contract (`LynxUsers` with `search()`, `find()`, `label()`), defaulting to the configured auth provider's model; assign roles.
* **Everywhere else:** buttons the user may not use are hidden (the page tree asks `Authorizer` per row, cached per request), and refusals that still happen show the core message.

## Build order (TDD throughout)

1. **Done.** Tables, models (`Role`, `RoleGrant`, `RoleAssignment`), `Authorizer`, `PermissionRegistry` (`lynx()->permissions()`), the superuser short circuit, and the rule that keeps signed in users' full rights until any user has a role (ADR-0033). Tests: unions of grants, template and branch scoping, anonymous actors, CLI and system actors.
2. **Done.** `permission()` on every action (null, the default, means superusers only; `*` everyone; `a|b` either), the runner's check before `handle()`, and `$context->authorize()` in page actions: `pages.save` (`pages.create` under the parent for the template, or `pages.edit`; `pages.publish` when publishing, a new page not created unpublished included; `pages.status` for hidden and locked), `pages.status`, `pages.move` (source and destination), `pages.sort`, `pages.delete` (every page of the branch), `pages.switch_template` (the page and the target template), `pages.translate`, `redirects.delete`. Actions run inside another are not checked again. Modules can delegate their branch: `lynx()->permissions()->delegate('menus', 'menus.manage')` lets menu managers run page actions under `/_menus/` only.
3. **Done.** Every Lynx permission is a Laravel ability `lynx.<permission>` (`Gate::allows('lynx.pages.edit', $page)`, `@can`), `accessLynxAdmin` defaults to `lynx.admin.access`, and the read tools check `pages.view`: anywhere for `find_pages` (results are not filtered by branch yet), on the page for `get_page` and `get_page_history`. MCP read tools act as `Actor::mcp(user)` like the write tools; the built-in agent already acts as its user.
4. Admin. **4a done:** core actions `roles.save`, `roles.delete`, `roles.assign` (superusers only, not offered as MCP or agent tools, never removing the last superuser assignment), the Roles screen (`/lynx/roles`, a grid of permissions with template and branch limits) and the Users screen (`/lynx/users`, host app users through `LynxUsers`, default `EloquentUsers` on the `users` auth provider's model), both superusers only and shown in the top bar only to them. **4b done:** `AdminAccess` answers for the actor the actions run as; the page tree shows Edit, New, Move, Delete and the status toggles only where allowed, the editor is read only without `pages.edit` and opens only with `pages.view`, the Settings tab shows only the sections the user may change, Templates and Fields need `schema.manage` and the menu editor `menus.manage`, and the top bar hides what the user cannot open.
5. **Done.** Install creates the `superuser` and `editor` roles; `lynx:install --user=ID` makes that user a superuser. No migration is needed for upgrades: until any user has a role, signed in users keep full rights. Schema export and apply carry roles and grants (branches as default-language paths), never assignments; `--force` deletes roles missing from the files.
6. **Done.** ADR-0033, the README's install section, the admin README and the agent usage guide.

## Risks

* **Locking everyone out on upgrade.** The upgrade migration must assign `superuser` to a configured user or keep the old "any signed in user" rule until one is assigned; covered by a test.
* **Performance.** Scoped checks run per row in the page tree. One query loads all grants per request; branch membership uses the closure table with the ids already on screen.
* **Branch grants and moves.** Moving a page out of a granted branch removes the editor's access to it. The move check runs at both source and destination, so an editor cannot move a page out of reach by accident.
