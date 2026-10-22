<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageSorted;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\SiblingOrder;

/**
 * Changes a page's position among its siblings. Use pages.move to change the parent.
 */
final class SortPage extends Action
{
    use ResolvesPages;

    public function __construct(private readonly PageGuard $guard, private readonly SiblingOrder $order) {}

    public static function name(): string
    {
        return 'pages.sort';
    }

    public static function permission(): string
    {
        return 'pages.move';
    }

    public static function description(): string
    {
        return 'Change the position of a page among its siblings (0 = first). Paths and parent stay the same.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'position' => ['required', 'integer', 'min:0']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'position' => $schema->integer()->description('0-based position among siblings. Values past the end place the page last.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertSortable($page);
        $context->authorize('pages.move', $page);

        if ($page->parentId === null) {
            throw ValidationException::withMessages(['page' => 'The home page has no siblings to sort.']);
        }

        $from = $this->order->positionOf($page->parentId, (int) $page->id);
        $page->sort = $this->order->place($page->parentId, (int) $page->id, (int) $input->int('position'));

        $context->changes->updated('page', $page->id, "Sort page {$page->name()} from position {$from} to {$page->sort}", [
            'from' => $from,
            'to' => $page->sort,
        ]);
        $context->event(new PageSorted((int) $page->id, $page->parentId, $from, $page->sort));

        return $page;
    }
}
