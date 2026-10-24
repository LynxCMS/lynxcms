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

A user without a role can do nothing in Lynx: the admin answers 403, and the actions they run are refused. `php artisan lynx:superuser` creates a superuser, or makes an existing account one, on a new site or on one where nobody is.

Visitors who are not signed in can run only the actions open to everyone, such as submitting a form. Code that writes on a visitor's behalf, such as a webhook that updates a page, says so:

```php
use LynxCms\Contracts\Actions\Actor;

lynx()->actingAs(Actor::system(), fn () => $session->save());
```

On your own machine, `LYNX_OPEN_UNTIL_ROLES=true` brings back the open mode of older releases: until any user has a role, every signed in user may do everything. Never set it on a server others can reach.

## People who sign up

Install also creates a `guest` role with no grants. If your app lets visitors sign up (Fortify, Breeze or your own registration), Lynx gives each new user the `guest` role when Laravel's `Registered` event fires. They see the website like any visitor, and the admin answers 403. Users who already hold a role keep their roles.

Set `LYNX_REGISTRATION_ROLE` to give sign-ups another role, such as one with grants of your own, or to `null` to give them none.

## Checking permissions

```php
Gate::allows('lynx.pages.edit', $page);
```

## Your own permissions

Register a permission in a service provider. It then shows in the Roles screen, and roles grant it like the built-in ones:

<<< @/snippets/review/BlogReviewServiceProvider.php#permission{php}

`requires` lists the permissions a role must grant alongside it. Saving a role that grants `blog.review` without `pages.view` fails with "[blog.review] needs [pages.view] in the same role." Pass `scoped: true` when grants should be limitable to templates and branches. In that case, check the permission against the page: `app(AdminAccess::class)->allows('blog.review', $page)`.

Check the permission where the work happens, not only where the link shows:

- Admin screens: `Admin::screen()` takes the permission and answers 403 without it.
- Livewire components: check in `boot()`, because every click is a new request ([example](/admin/extending#example-reviewing-blog-posts)).
- Actions: return the permission from `permission()`, and the runner checks it for the PHP API, the admin, MCP and the agent.
- Read tools for MCP and the agent: return it from `permission()` too. A read tool that names none needs `pages.view`.

A permission guards your code, and actions guard their own writes. A user with `blog.review` but not `pages.edit` can open the review screen, but cannot save a post from it.

## Agents and MCP clients

Agents act with the roles of the user they work for. MCP clients that act for no user get `LYNX_ANONYMOUS_ROLE`; without it they are refused.
