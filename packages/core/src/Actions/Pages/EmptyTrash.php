<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Deletes trashed branches for good (ADR-0035): all of them, or one. A branch holding a locked
 * page stays, and the result names its locked pages so they can be unlocked; a branch the user
 * may not delete stays too. Everything else is deleted, never a general failure.
 */
final class EmptyTrash extends Action
{
    public function __construct(private readonly Trash $trash) {}

    public static function name(): string
    {
        return 'pages.empty_trash';
    }

    public static function permission(): string
    {
        return 'pages.delete';
    }

    public static function description(): string
    {
        return 'Permanently delete the pages in the trash, or one trashed branch. Branches with locked pages are kept and listed under "blocked" with their locked page ids.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['page' => ['sometimes', 'nullable', 'integer']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->integer()->description('Id of one trashed branch (its top page). Omit to empty the whole trash.')];
    }

    /**
     * @return array{deleted: list<int>, blocked: list<array{page: int, locked: list<int>}>, denied: list<int>}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $result = ['deleted' => [], 'blocked' => [], 'denied' => []];

        foreach ($this->branches($input->int('page')) as $top) {
            $ids = Value::ints(DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $top)->pluck('descendant_id')->all());
            $locked = $this->locked($ids);

            if ($locked !== []) {
                $result['blocked'][] = ['page' => $top, 'locked' => $locked];

                continue;
            }

            try {
                $context->authorizePages('pages.delete', $ids);
            } catch (AuthorizationException) {
                $result['denied'][] = $top;

                continue;
            }

            $context->run(DeletePage::class, ['page' => $top]);
            $result['deleted'][] = $top;
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function branches(?int $page): array
    {
        $branches = $this->trash->branches();

        if ($page === null) {
            return $branches;
        }

        return in_array($page, $branches, true)
            ? [$page]
            : throw ValidationException::withMessages(['page' => "Page [{$page}] is not the top of a trashed branch."]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function locked(array $ids): array
    {
        $statuses = array_map(Value::int(...), DB::table(Tables::PAGES)->whereIn('id', $ids)->orderBy('id')->pluck('status', 'id')->all());

        return array_keys(array_filter($statuses, fn (int $status): bool => ($status & PageStatus::LOCKED) !== 0));
    }
}
