---
title: Users
description: How Lynx refers to your app's users, the columns it adds to your users table, and what to do when you delete a user.
---

# Users

Lynx works with the users your app already has. Your app creates them, signs them in, and deletes them. Lynx gives them roles and records what they change, and it keeps only their ids for that.

## User ids

Lynx keeps a user's id as a string in its own tables, with no foreign key to your users table. Any kind of id fits: an auto-incrementing integer, a UUID, a ULID, or an id from a user provider that is not Eloquent. Lynx reads it from the user's `getAuthIdentifier()`, the method every Laravel user model has.

These columns hold user ids:

| Table | Columns | Holds |
| --- | --- | --- |
| `lynx_role_user` | `user_id`, `assigned_by` | Who holds each role, and who gave it |
| `lynx_pages` | `created_by`, `modified_by` | Who created and last changed each page |
| `lynx_page_languages` | `updated_by` | Who last changed a page's name or status in a language |
| `lynx_strings` | `updated_by` | Who last changed each site text |
| `lynx_revisions` | `created_by` | Who saved each revision |
| `lynx_schedules` | `created_by` | Who scheduled each publish or unpublish |
| `lynx_activity_events` | `actor_kind`, `actor_id` | Who ran each action |

The author columns (`assigned_by`, `created_by`, `modified_by`, and `updated_by`) record who made the change along with the user's id, such as `user:9` for a person or `mcp:9` for an MCP client acting for that person. The activity log keeps the two parts in separate columns.

## Reading users

Lynx never joins its tables to your users table. It looks users up through Laravel's authentication instead:

- The admin shows a user's name (in the activity log, a page's history and revisions, and the page tree's details) through the user provider's `retrieveById()`, once per user and request. When the user no longer exists, it shows "User 9".
- Setup › Users finds users through `LynxUsers`, an interface in the admin package. The default implementation searches the Eloquent model that `auth.providers.users.model` names, by name and email. An app that keeps its users somewhere else binds its own implementation of `LynxUsers` in a service provider.

Your own code can do the same. To list the users who hold a role, read the ids with `RoleAssignment::query()->pluck('user_id')` first, then load the users with `whereKey()` in a second query. A single query that joins the two tables needs a cast on PostgreSQL, which refuses to compare a string column with an integer one.

## Columns on your users table

Lynx adds a few columns to your users table when its migrations run:

- `lynx_language_id`: the site language the user last chose with `lynx()->languages->setCurrent($code, remember: true)`. Requests whose URL has no language prefix, such as the admin, form posts, and your own routes, use it when the session holds no language. It goes on the table of the model that `auth.providers.users.model` names. The language of the admin's own interface is a separate setting, which Lynx keeps in its own tables.
- `two_factor_secret`, `two_factor_recovery_codes`, and `two_factor_confirmed_at`: what two-factor authentication needs, the same columns Laravel Fortify's own migration adds.

The admin also creates a `passkeys` table with a foreign key to your users table, so a user's passkeys go when the user does. The admin's migrations skip the two-factor columns and the `passkeys` table when your app already has them, so apps that publish Fortify's migrations keep their own.

## Deleting a user

Without a foreign key, deleting a user leaves their roles behind in `lynx_role_user`. Nobody can sign in as a deleted user, so the rows grant nothing. Most apps can leave them there: auto-incrementing ids, UUIDs, and ULIDs are never given to another user.

The rows matter when an id can come back, such as an id that an external sign-in provider assigns. A new user with an old id would get the old user's roles. An app like that removes a user's roles when it deletes the user. This listener sets the user's roles to none:

<<< @/snippets/advanced/ForgetLynxRoles.php{php}

Register it in the `booted()` method of your user model:

```php
protected static function booted(): void
{
    static::deleting(new ForgetLynxRoles);
}
```

The listener runs as the system, since the person deleting the account may not hold the right to change roles. It runs before the row goes, because Lynx never removes the last superuser: when the user is the site's only superuser, the action fails and the user is not deleted.

If your user model uses soft deletes, register the listener with `static::forceDeleting()` instead. `deleting` also runs on a soft delete, and a restored user would come back without their roles.

The author columns and the activity log keep the deleted user's id on purpose, so the history of each page stays complete.
