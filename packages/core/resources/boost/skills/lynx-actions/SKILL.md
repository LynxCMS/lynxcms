---
name: lynx-actions
description: Change Lynx content and schema safely through actions, hooks and permissions. Use when writing code that saves, moves, publishes, schedules or deletes pages, reacts to page changes, adds a custom action, or checks Lynx permissions.
---

# Lynx actions

Every write in Lynx is an action, run by the action runner: validated, wrapped in a transaction, checked against permissions, logged as activity, and available as an MCP and agent tool.

## Writing

```php
$page = lynx()->pages->get('/courses/delivery-8/');
$page->price = 199;
$page->save();

lynx()->pages->move($page, '/archive/');
lynx()->pages->setStatus($page, add: ['hidden']);
lynx()->pages->schedule($page, 'pt', publishAt: now()->addDay());
lynx()->pages->saveDraft($page, ['title' => ['pt' => 'Novo título']]);
lynx()->pages->trash($page);            // restorable; prefer it to delete()

$result = lynx()->pages->delete($page, dryRun: true); // see what would change
```

Never update Lynx tables directly, and never call an action's `handle()` yourself.

## Reacting to changes

```php
lynx()->hook(\LynxCms\Core\Actions\Pages\SavePage::class)->after(function ($page) {
    // runs inside the transaction; throw ActionHalted to roll the save back
});
```

Domain events (`PageSaved`, `PageMoved`, `PageTrashed`, `PageDeleted`) are dispatched after the transaction commits.

## Acting as someone

```php
use LynxCms\Contracts\Actions\Actor;

lynx()->actingAs(Actor::user($userId), fn () => lynx()->pages->trash('/courses/old/'));
```

Permissions follow the actor's roles (`pages.edit`, `pages.publish`, `pages.delete`…, limited to templates and branches). In Blade or policies: `Gate::allows('lynx.pages.edit', $page)`.

## Custom actions

Extend `LynxCms\Core\Actions\Action` (final class, `handle(Input $input, ActionContext $context)`, static `name()`, `description()`, `rules()`, `schema()`, `permission()`), record changes with `$context->changes`, call other actions with `$context->run()`, and register the class with `lynx()->actions()->register()` in a service provider.
