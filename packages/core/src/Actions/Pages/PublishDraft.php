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
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Strings\PageStringEdits;
use LynxCms\Core\Support\Tables;

/**
 * Makes a page's draft live (ADR-0039): its values and its own template strings (ADR-0048) are
 * saved onto the page through pages.save, so validation, hooks, activity and a revision apply,
 * and the draft is deleted.
 */
final class PublishDraft extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly Drafts $drafts,
        private readonly PageGuard $guard,
        private readonly PageLoader $loader,
        private readonly Revisions $revisions,
        private readonly PageStringEdits $strings,
    ) {}

    public static function name(): string
    {
        return 'pages.publish_draft';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Make the page\'s draft live: its values replace the page\'s, and the draft is deleted.';
    }

    public static function rules(): array
    {
        return ['page' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Id, default-language path or page name of the live page.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $draftId = $this->drafts->of((int) $page->id) ?? throw ValidationException::withMessages(['page' => "Page [{$page->name()}] has no draft."]);
        $this->guard->assertEditable($page);
        $context->authorize('pages.edit', $page);

        $draft = $this->loader->find($draftId) ?? throw ValidationException::withMessages(['page' => "The draft of page [{$page->name()}] could not be read."]);
        $saved = $context->run(SavePage::class, [
            'id' => $page->id,
            'values' => $this->revisions->saveInput($page, $draft->valuesByLanguage()),
            'strings' => $this->strings->replacement((int) $page->id, $this->strings->snapshot($draftId)),
        ]);
        $this->drafts->delete($draftId);
        // A draft published or discarded by hand no longer has a time to go live (ADR-0092).
        DB::table(Tables::SCHEDULES)->where('page_id', $page->id)->where('action', SchedulePage::PUBLISH_DRAFT)->delete();
        $this->loader->forget($draftId);
        $context->changes->updated('page', $page->id, "Publish the draft of page {$page->name()}", ['draft' => $draftId]);

        return $saved instanceof Page ? $saved : $page;
    }
}
