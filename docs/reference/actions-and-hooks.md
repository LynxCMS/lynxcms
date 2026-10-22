---
title: Actions and hooks
description: Run, preview and react to writes, act as a user and add your own actions.
order: 1
---

# Actions and hooks

Every write in Lynx is an action, run by the action runner: validated, checked against permissions, wrapped in a transaction, logged as activity, and offered as an MCP and agent tool.

## Running actions

The PHP API runs actions for you:

```php
$page = lynx()->pages->get('/courses/delivery-8/');
$page->price = 199;
$page->save();

lynx()->pages->move($page, '/archive/');
$result = lynx()->pages->delete($page, dryRun: true); // the changes it would make
```

Any action also runs by class:

```php
use LynxCms\Core\Actions\Pages\SavePage;

$result = lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['price' => 199]]);
```

Never write to Lynx's tables directly, and never call an action's `handle()` yourself.

## Hooks

```php
use LynxCms\Core\Actions\Pages\SavePage;

lynx()->hook(SavePage::class)->after(function ($page) {
    // runs inside the transaction; throw ActionHalted to roll the save back
});
```

Domain events (`PageSaved`, `PageMoved`, `PageTrashed`, `PageDeleted` and others) are dispatched after the transaction commits, never on dry runs.

Every event that can change what visitors see implements `ContentChanged`, so one listener covers saving, publishing (`PageStatusChanged`), moving, sorting, trashing, deleting and restoring pages, switching templates (`PageTemplateSwitched`), site texts (`SiteStringsSaved`) and languages:

```php
use Illuminate\Support\Facades\Event;
use LynxCms\Core\Events\ContentChanged;

Event::listen(ContentChanged::class, function (ContentChanged $event) {
    $pages = $event->affectedPages(); // page ids, or null when any page may show the change
});
```

`affectedPages()` lists the pages whose own content or list of children changed. Pages that only show a changed page through a page reference are not included.

## Acting as someone

```php
use LynxCms\Contracts\Actions\Actor;

lynx()->actingAs(Actor::user($userId), fn () => lynx()->pages->trash('/courses/old/'));
```

Permissions follow that user's roles. Console commands run as `cli`.

## Your own actions

```php
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

final class ArchiveCourse extends Action
{
    public static function name(): string { return 'courses.archive'; }
    public static function description(): string { return 'Move a course to the archive and hide it.'; }
    public static function permission(): ?string { return 'pages.move'; }
    public static function rules(): array { return ['page' => ['required']]; }
    public static function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('The course page.')->required()];
    }

    public function handle(Input $input, ActionContext $context): mixed
    {
        $page = $context->run(\LynxCms\Core\Actions\Pages\MovePage::class, ['page' => $input->get('page'), 'parent' => '/archive/']);
        $context->changes->updated('page', $page->id, "Archive {$page->name}");

        return $page;
    }
}
```

Register it in a service provider with `lynx()->actions()->register(ArchiveCourse::class)`. It becomes an MCP and agent tool unless `exposedAsTool()` returns `false`. `permission()` returning `null` means superusers only. Mark actions that remove data with `destructive()`.
