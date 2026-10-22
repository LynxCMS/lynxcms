<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageDeleted;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Permanently deletes a page and everything under it (trash comes later).
 */
final class DeletePage extends Action
{
    use ResolvesPages;

    public function __construct(private readonly PageGuard $guard, private readonly FieldRegistry $fields) {}

    public static function name(): string
    {
        return 'pages.delete';
    }

    public static function permission(): string
    {
        return 'pages.delete';
    }

    public static function description(): string
    {
        return 'Permanently delete a page and all pages under it, with all their values in every language.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['page' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Id, default-language path or page name of the page.')->required()];
    }

    /**
     * @return list<int>
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = $this->resolvePageReference($input->get('page'));

        if ($page->parentId === null) {
            throw ValidationException::withMessages(['page' => 'The home page cannot be deleted.']);
        }

        $ids = Value::ints(DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $page->id)->orderByDesc('depth')
            ->pluck('descendant_id')->all());
        $this->guard->assertDeletable($page, $ids);

        // Every page of the branch, so a branch or template limited grant cannot delete beyond it.
        $context->authorizePages('pages.delete', $ids);

        foreach ($this->fields->all() as $field) {
            DB::table($field->table())->whereIn('page_id', $ids)->delete();
        }

        DB::table(Tables::FIELD_VALUE_META)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::PAGE_PATHS)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::STRINGS)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::PAGE_LANGUAGES)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::PAGE_CLOSURE)->whereIn('descendant_id', $ids)->delete();
        DB::table(Tables::TRASH)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::SCHEDULES)->whereIn('page_id', $ids)->delete();
        DB::table(Tables::REVISIONS)->whereIn('page_id', $ids)->delete();

        foreach (DB::table(Tables::PAGES)->whereIn('draft_of', $ids)->pluck('id') as $draft) {
            app(Drafts::class)->delete(Value::int($draft));
        }

        foreach ($ids as $id) {
            DB::table(Tables::PAGES)->where('id', $id)->delete();
        }

        $context->changes->deleted('page', $page->id, "Delete page {$page->name()} and ".(count($ids) - 1).' page(s) under it', ['ids' => $ids]);
        $context->event(new PageDeleted($ids));

        return $ids;
    }
}
