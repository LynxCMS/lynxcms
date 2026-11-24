---
title: Roles and permissions
description: Roles, grants limited to templates and branches, and the anonymous role.
order: 2
---

# Roles and permissions

Roles decide what each person may do. A role can describe editors who edit and publish pages, translators who edit but can't publish, or a client who works only on the pages of their own section of the site. The same rules apply in the admin, in your code and to AI agents.

A role is a set of **grants**. A grant is a permission, optionally limited to some templates and to branches of the page tree (a page and the pages below it). Users can hold several roles. A superuser role allows everything.

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
| `search.manage` | Rebuild the search index and see what it holds, in the admin's Search index screen and with `search_inspect`. |
| `agent.use` | Use the built-in agent and approve its plans. |
| `system.manage` | Change system pages. |
| `menus.manage` | Manage menus (with `lynxcms/menus`). |

The admin's **Roles** screen edits roles and **Users** assigns them. In code, run this once, in `php artisan tinker` (Laravel's interactive shell) or a seeder (a class that fills the database, run with `php artisan db:seed`):

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

Visitors who are not signed in can run only the actions open to everyone, such as submitting a form. Code that writes on a visitor's behalf, such as the controller of a webhook that updates a page, says so:

```php
use LynxCms\Contracts\Actions\Actor;

lynx()->actingAs(Actor::system(), fn () => $session->save());
```

## People who sign up

If your app lets visitors sign up (Fortify, Breeze or your own registration), the new users get no role. A user without a role has no rights, so they see the website like any visitor, and the admin answers 403.

Lynx never gives a role on sign-up. To give members a role of your own, assign it in your registration code, such as Fortify's `CreateNewUser` in `app/Actions/Fortify/CreateNewUser.php`, after the user is created:

```php
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;

lynx()->actingAs(Actor::system(), fn () => lynx()->run(AssignRoles::class, [
    'user' => (string) $user->id,
    'roles' => ['member'],
]));
```

Running it as the system records the assignment in the activity log. Anyone can sign up, so give this role no `admin.access` and never make it a superuser. Staff get their roles from Setup › Users, by invitation or with a temporary password.

## Checking permissions

Check a permission anywhere in PHP code, such as a controller or a Livewire component:

```php
Gate::allows('lynx.pages.edit', $page);
```

In Blade, `@can('lynx.pages.edit', $page)` does the same. [Showing an edit link to editors](/basics/views#showing-an-edit-link-to-editors) explains how the answer depends on the page you pass.

## Your own permissions

Register a permission in the `boot()` method of a service provider such as `app/Providers/AppServiceProvider.php`. A service provider is a Laravel class whose `boot()` method runs on every request, before your pages. The permission then shows in the Roles screen, and roles grant it like the built-in ones:

<<< @/snippets/review/BlogReviewServiceProvider.php#permission{php}

`requires` lists the permissions a role must grant alongside it. Saving a role that grants `blog.review` without `pages.view` fails with "[blog.review] needs [pages.view] in the same role." Pass `scoped: true` when grants should be limitable to templates and branches. In that case, check the permission against the page: `app(AdminAccess::class)->allows('blog.review', $page)`.

Check the permission where the work happens, not only where the link shows:

- Admin screens: `Admin::screen()` takes the permission and answers 403 without it.
- Livewire components: check in `boot()`, because every click is a new request ([example](/admin/extending#example-reviewing-blog-posts)).
- Actions: return the permission from `permission()`, and the runner checks it for the PHP API, the admin, MCP and the agent.
- Read tools for MCP and the agent: return it from `permission()` too. A read tool that names none needs `pages.view`.

A permission guards your code, and actions guard their own writes. A user with `blog.review` but not `pages.edit` can open the review screen, but cannot save a post from it.

## Agents and MCP clients

Agents act with the roles of the user they work for. MCP clients and `lynx:ask`, which act for no user, get `LYNX_ANONYMOUS_ROLE`; without it they are refused.
