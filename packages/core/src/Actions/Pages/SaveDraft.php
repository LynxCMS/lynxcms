<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\EditLocks;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use UnexpectedValueException;

/**
 * Saves field values and the page's own template strings (ADR-0048) to the page's draft
 * (ADR-0039), creating it from the live page the first time. The live page does not change until
 * pages.publish_draft. Values are validated like a save.
 */
final class SaveDraft extends Action
{
    use ResolvesPages;

    public function __construct(private readonly Drafts $drafts, private readonly PageGuard $guard) {}

    public static function name(): string
    {
        return 'pages.save_draft';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Save field values or template texts to the page\'s draft without changing the live page; pages.publish_draft makes them live. Values and strings take the same form as in pages.save.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'values' => ['required_without:strings', 'array'], 'strings' => ['sometimes', 'array']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the live page.')->required(),
            'values' => $schema->object()->description('Field values, as in pages.save: {"field": value} or {"field": {"code": value}} for translatable fields.'),
            'strings' => $schema->object()->description('Template texts, as in pages.save: {"pt": {"hero.title": "Texto"}}.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertEditable($page);
        $context->authorize('pages.edit', $page);

        $draft = $this->drafts->of((int) $page->id) ?? $this->drafts->create($page, EditLocks::holder($context->actor));
        // Recorded before the draft's own save: the activity log files an action under its first change, so under the live page.
        $context->changes->updated('page', $page->id, "Save draft of page {$page->name()}", ['draft' => $draft]);
        $saved = $context->run(SavePage::class, ['id' => $draft, 'values' => $input->array('values'), 'strings' => $input->array('strings')]);

        return $saved instanceof Page ? $saved : throw new UnexpectedValueException('pages.save did not return the draft.');
    }
}
