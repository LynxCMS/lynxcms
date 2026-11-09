<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageSorted;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\SiblingOrder;
use LynxCms\Core\Query\PageFinder;

/**
 * Puts every child of a page in one order at once: by title, or by when they were created, last
 * changed or published, each either way. Every status is sorted; system pages stay last, the trash
 * at the end, as pages.sort keeps them. Each child must be one the user may move.
 */
final class SortChildren extends Action
{
    use ResolvesPages;

    /** What the children can be sorted by: the selector's sort keys. */
    public const array ORDERS = ['title', 'created', 'modified', 'published'];

    public function __construct(
        private readonly PageGuard $guard,
        private readonly SiblingOrder $order,
        private readonly PageFinder $finder,
    ) {}

    public static function name(): string
    {
        return 'pages.sort-children';
    }

    public static function description(): string
    {
        return 'Sort all children of a page at once by title, created, modified or published date, ascending or descending. System pages stay last.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'by' => ['required', 'string', Rule::in(self::ORDERS)],
            'descending' => ['sometimes', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the parent.')->required(),
            'by' => $schema->string()->enum(self::ORDERS)->description('title (A to Z), created, modified or published (oldest first).')->required(),
            'descending' => $schema->boolean()->description('Reverse the order: Z to A, or newest first.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $parent = $this->resolvePageReference($input->get('page'));
        $by = (string) $input->string('by');
        $children = $this->finder->find('parent='.$parent->id.', include=all, sort='.($input->bool('descending') ? '-' : '').$by.', sort=id');

        foreach ($children as $child) {
            $this->guard->assertSortable($child);
            $context->authorize('pages.move', $child);
        }

        $moved = $this->order->arrange((int) $parent->id, $children->ids());

        foreach ($moved as $id => [$from, $to]) {
            $context->event(new PageSorted($id, (int) $parent->id, $from, $to));
        }

        $context->changes->updated('page', $parent->id, "Sort the children of {$parent->name()} by {$by}".($input->bool('descending') ? ', descending' : '').'; '.count($moved).' moved', [
            'by' => $by,
            'descending' => $input->bool('descending'),
            'moved' => array_keys($moved),
        ]);

        return $parent;
    }
}
