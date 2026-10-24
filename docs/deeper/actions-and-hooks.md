---
title: Actions and Hooks
description: Run and preview changes, act as a user, add business rules with hooks, react to events and write your own actions.
---

# Actions and Hooks

Every change in Lynx, such as saving a page, moving it or adding a language, is an **action**. The admin, your code, Artisan commands, imports, the MCP server and the AI agent all run the same actions, through one runner that:

1. validates the input;
2. checks the user's permissions;
3. runs the change in a database transaction, with the *before* and *after* hooks your code registers;
4. records what changed, for the activity log, revisions and dry runs;
5. dispatches events once the transaction has committed.

Because every path goes through the runner, a rule you add here applies everywhere at once.

## Running actions

The page API runs actions for you, so most code never names them:

```php
$post = lynx()->pages->get('/blog/hello-world');
$post->summary = 'Our first post.';
$post->save();                                    // pages.save

lynx()->pages->setStatus($post, add: ['hidden']); // pages.status
lynx()->pages->trash($post);                      // pages.trash
```

Any action also runs by its class, with its input as an array:

```php
use LynxCms\Core\Actions\Pages\SavePage;

lynx()->run(SavePage::class, ['id' => $post->id, 'values' => ['summary' => 'Our first post.']]);
```

Never write to Lynx's tables directly, and never call an action's `handle()` method yourself: both skip validation, permissions, hooks and the activity log.

### Dry runs

Every action can run as a **dry run**: it does all its work in a transaction, then rolls it back and returns what it would have changed. Use it to preview a change, to show a summary before a destructive step, or in tests:

```php
$result = lynx()->pages->delete('/blog', dryRun: true);

foreach ($result->changes->summary() as $line) {
    echo $line, PHP_EOL; // "Delete page blog and 2 page(s) under it"
}
```

Events are never dispatched for a dry run, so listeners don't send mail or clear caches for a change that didn't happen.

### Acting as a user

Code runs with the permissions of the signed in user. In a web request with nobody signed in, it runs as a visitor, who may only run actions open to everyone. Artisan commands, queue workers and the scheduler run as `cli`, which may do everything. To run code as a specific user, for example in a queued job, or as the system for a visitor, use `actingAs()`:

```php
use LynxCms\Contracts\Actions\Actor;

lynx()->actingAs(Actor::user($userId), fn () => lynx()->pages->trash('/blog/old-news'));
```

The action then checks that user's roles, and the activity log names them.

## Hooks

Hooks run inside an action's transaction: **before** hooks run before the action does its work, and **after** hooks run once it has done it, before the transaction commits. An exception thrown from either rolls everything back, so nothing is written:

- a `ValidationException` keyed by a field, such as `values.summary`, is shown on that field in the page editor, and returned as a validation error to the API, MCP clients and the agent;
- `ActionHalted` stops the action with a message, for rules that are not about one field.

Register hooks in a service provider's `boot()` method.

### A business rule for every way of saving

An after hook receives the action's result, so a rule can look at the saved page whoever saved it: an editor in the admin, an import, or an AI agent:

<<< @/snippets/advanced/hooks.php#publish-rule{php}

In the page editor, the message appears under the Summary field, and the save doesn't happen. An AI agent's plan fails the same way, before anything is applied.

### Before hooks and the input

A before hook receives the action's **input**, and may return a changed copy with `$input->with()`. The input is what the caller sent, so its shape depends on the caller: the admin and the API send `SavePage` an `id` and `values`, while `$page->save()` sends the `Page` itself, as `page`. A before hook that adds a value should handle both, which is why rules like the one above are usually easier to write as after hooks.

```php
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\Input;

lynx()->hook(SavePage::class)->before(function (Input $input): void {
    logger()->info('Saving a page', ['page' => $input->get('id') ?? $input->get('page')?->id]);
});
```

`'*'` hooks every action: `lynx()->hook('*')->after(...)`.

## Events

Hooks run inside the transaction. For side effects, such as sending mail, clearing a cache or calling another service, listen to **events** instead: they are dispatched only after the transaction has committed, and never for a dry run.

<<< @/snippets/advanced/hooks.php#notify{php}

| Event | Dispatched when |
| --- | --- |
| `PageSaved` | A page is created (`$event->created`) or saved. |
| `PageMoved`, `PageSorted` | A page moves to another parent, or another place among its siblings. |
| `PageStatusChanged` | A page is published, unpublished, hidden or locked. |
| `PageTrashed`, `PageRestored`, `PageDeleted` | A page goes to the trash, comes back, or is deleted for good. |
| `PageTemplateSwitched` | A page switches to another template. |
| `SiteStringsSaved` | Editors change site texts. |

Every event that can change what visitors see implements `ContentChanged`, so one listener can react to all of them, for example to purge an external cache:

```php
use Illuminate\Support\Facades\Event;
use LynxCms\Core\Events\ContentChanged;

Event::listen(ContentChanged::class, function (ContentChanged $event) {
    $pageIds = $event->affectedPages(); // the page ids, or null when any page may show the change
});
```

`affectedPages()` lists the pages whose own content or list of children changed. Pages that only show a changed page through a page reference are not included.

## Writing your own actions

When a change is part of your site's language, such as "archive a post" or "close enrolments", make it an action of its own. It gets validation, permissions, dry runs, the activity log and hooks, and the MCP server and the AI agent offer it as a tool automatically.

<<< @/snippets/advanced/ArchivePost.php#action{php}

An action has:

| Method | Gives |
| --- | --- |
| `name()` | Its name, such as `posts.archive`, used in the activity log and as the tool's name. |
| `description()` | What it does. AI tools read it to decide when to use the action. |
| `permission()` | The permission it needs. `null` allows superusers only. |
| `rules()`, `schema()` | Laravel validation rules for its input, and the input's JSON schema for AI tools. |
| `handle()` | The work. Run other actions with `$context->run()`, so they share its transaction and log entry. Actions run this way are not checked again, so check every page your action passes on, such as one named in its input, with `$context->authorize('pages.edit', $page)`. |
| `destructive()` | Return `true` for actions that remove data. AI tools are told to run them as a dry run first and ask the user to confirm. |
| `exposedAsTool()` | Return `false` to keep an action out of the MCP server and the agent. |

Register it in a service provider:

```php
use App\Lynx\Actions\ArchivePost;

public function boot(): void
{
    lynx()->actions()->register(ArchivePost::class);
}
```

Then run it like any other action, including as a dry run:

```php
lynx()->run(ArchivePost::class, ['post' => '/blog/hello-world']);
lynx()->run(ArchivePost::class, ['post' => '/blog/hello-world'], dryRun: true);
```
