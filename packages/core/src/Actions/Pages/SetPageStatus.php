<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageStatusChanged;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\UniqueNames;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Adds or removes status flags without saving field values, so a page with an incomplete
 * required field can still be hidden, published or locked (the page tree's quick actions).
 * A flag listed in both add and remove ends up removed.
 */
final class SetPageStatus extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageGuard $guard,
        private readonly UniqueNames $uniqueNames,
        private readonly LanguageRegistry $languages,
    ) {}

    /** Flags editors may toggle. System is set by installers, not by editors. */
    public const array EDITABLE = ['hidden', 'unpublished', 'locked', 'unique'];

    public static function name(): string
    {
        return 'pages.status';
    }

    public static function permission(): string
    {
        return 'pages.status|pages.publish';
    }

    public static function description(): string
    {
        return 'Hide, unhide, publish, unpublish, lock, unlock or mark unique a page by adding or removing status flags. '
            .'A unique page\'s default-language name is used by no other page anywhere. '
            .'A locked page only lets "locked" change; system pages cannot change status.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'add' => ['sometimes', 'array'],
            'add.*' => [Rule::in(self::EDITABLE)],
            'remove' => ['sometimes', 'array'],
            'remove.*' => [Rule::in(self::EDITABLE)],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        $flags = $schema->array()->items($schema->string()->enum(self::EDITABLE));

        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'add' => $flags->description('Flags to set, e.g. ["hidden"].'),
            'remove' => (clone $flags)->description('Flags to clear, e.g. ["unpublished"] to publish.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $add = PageStatus::fromNames(Value::strings($input->array('add')));
        $remove = PageStatus::fromNames(Value::strings($input->array('remove')));
        $this->guard->assertStatusChange($page, $add | $remove);

        if ((($add | $remove) & PageStatus::UNPUBLISHED) !== 0) {
            $context->authorize('pages.publish', $page);
        }

        if ((($add | $remove) & ~PageStatus::UNPUBLISHED) !== 0) {
            $context->authorize('pages.status', $page);
        }

        if ($add === 0 && $remove === 0) {
            throw ValidationException::withMessages(['add' => 'Give at least one flag to add or remove.']);
        }

        $from = $page->status;
        $page->status = ($from | $add) & ~$remove;

        if ($page->hasStatus(PageStatus::UNIQUE) && ($from & PageStatus::UNIQUE) === 0) {
            $this->uniqueNames->assertAllowed($page->id, $page->name($this->languages->default()), isUnique: true, key: 'add');
        }
        $isPublishing = ($from & PageStatus::UNPUBLISHED) !== 0 && ! $page->hasStatus(PageStatus::UNPUBLISHED);

        $row = ['status' => $page->status, 'updated_at' => now(), 'revision' => DB::raw('revision + 1')];

        if ($isPublishing && $page->publishedAt === null) {
            $row['published_at'] = now();
        }

        DB::table(Tables::PAGES)->where('id', $page->id)->update($row);

        $context->changes->updated('page', $page->id, "Set status of page {$page->name()} to [".implode(', ', PageStatus::toNames($page->status)).']', [
            'from' => PageStatus::toNames($from),
            'to' => PageStatus::toNames($page->status),
        ]);
        $context->event(new PageStatusChanged((int) $page->id, PageStatus::toNames($page->status & ~$from), PageStatus::toNames($from & ~$page->status)));

        return $page;
    }
}
