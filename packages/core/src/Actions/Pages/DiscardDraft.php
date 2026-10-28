<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Support\Tables;

/** Deletes a page's draft (ADR-0039); the live page stays as it is. */
final class DiscardDraft extends Action
{
    use ResolvesPages;

    public function __construct(private readonly Drafts $drafts) {}

    public static function name(): string
    {
        return 'pages.discard_draft';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Delete the page\'s draft. The live page does not change.';
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
        return ['page' => $schema->string()->description('Id, default-language path or page name of the live page.')->required()];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $page = $this->resolvePageReference($input->get('page'));
        $draftId = $this->drafts->of((int) $page->id) ?? throw ValidationException::withMessages(['page' => "Page [{$page->name()}] has no draft."]);
        $context->authorize('pages.edit', $page);

        $this->drafts->delete($draftId);
        // A draft published or discarded by hand no longer has a time to go live (ADR-0092).
        DB::table(Tables::SCHEDULES)->where('page_id', $page->id)->where('action', SchedulePage::PUBLISH_DRAFT)->delete();
        // The live page's change first, so the activity log files the discard under it, not under the draft.
        $context->changes->updated('page', $page->id, "Discard the draft of page {$page->name()}", ['draft' => $draftId]);
        $context->changes->deleted('page', $draftId, "Delete draft {$draftId} of page {$page->name()}", ['page' => $page->id]);

        return true;
    }
}
