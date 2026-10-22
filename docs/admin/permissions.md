---
title: Roles and permissions
description: Roles, grants limited to templates and branches, and the anonymous role.
order: 2
---

# Roles and permissions

A role is a set of grants. A grant is a permission, optionally limited to some templates and to branches of the page tree. Users can hold several roles. A superuser role allows everything.

| Permission | Allows |
| --- | --- |
| `admin.access` | Open the admin. |
| `pages.view` | See pages and unpublished content in the admin and agent read tools. |
| `pages.edit` | Edit pages, translate them, delete old URLs. |
| `pages.create` | Create pages. |
| `pages.publish` | Publish and unpublish pages. |
| `pages.status` | Hide, unhide, lock and unlock pages. |
| `pages.move` | Move and sort pages. |
| `pages.delete` | Delete pages. |
| `pages.template` | Switch the template of pages. |
| `schema.manage` | Manage fields, templates and languages. |
| `agent.use` | Use the built-in agent and approve its plans. |
| `system.manage` | Change system pages. |
| `menus.manage` | Manage menus (with `lynxcms/menus`). |

The admin's **Roles** screen edits roles and **Users** assigns them. In code:

```php
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

lynx()->run(SaveRole::class, ['name' => 'course-editor', 'grants' => [
    ['permission' => 'admin.access'],
    ['permission' => 'pages.edit', 'branches' => [$coursesId]],
]]);
lynx()->run(AssignRoles::class, ['user' => '42', 'roles' => ['course-editor']]);
```

Until any user has a role, every signed in user may do everything.

## Checking permissions

```php
Gate::allows('lynx.pages.edit', $page);
```

Modules register their own permissions with `lynx()->permissions()->register('reports.view', 'View reports')`.

## Agents and MCP clients

Agents act with the roles of the user they work for. MCP clients that act for no user get `LYNX_ANONYMOUS_ROLE`; without it they are refused once roles are in use.
