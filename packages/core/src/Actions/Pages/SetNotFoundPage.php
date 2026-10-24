<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\NotFoundPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Support\Value;

/**
 * Picks the page shown, with status 404, for URLs that match nothing (ADR-0085), or none, for
 * Laravel's own 404. Superusers only: it changes what every missing URL answers.
 */
final class SetNotFoundPage extends Action
{
    use ResolvesPages;

    public function __construct(private readonly RootPage $root, private readonly Trash $trash) {}

    public static function name(): string
    {
        return 'pages.set_not_found';
    }

    public static function description(): string
    {
        return 'Choose the page shown, with status 404, for URLs that match no page; or none, for the framework\'s plain 404.';
    }

    public static function rules(): array
    {
        return ['page' => ['present', 'nullable']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Id, default-language path or page name of the page; null for none.')->required()];
    }

    public function handle(Input $input, ActionContext $context): ?Page
    {
        $reference = $input->get('page');
        $page = $reference === null || $reference === '' ? null : $this->resolvePageReference($reference);

        if ($page !== null) {
            $this->assertShowable($page);
        }

        Setting::write(NotFoundPage::SETTING, $page === null ? null : (string) $page->id);
        $context->changes->updated('setting', NotFoundPage::SETTING, $page === null
            ? 'Show the plain 404 for URLs that match no page'
            : "Show [{$page->name()}] for URLs that match no page", ['page' => $page?->id]);

        return $page;
    }

    private function assertShowable(Page $page): void
    {
        $problem = match (true) {
            $page->id === $this->root->id() => 'The home page cannot be the page not found.',
            $this->trash->holds(Value::int($page->id)) => 'That page is in the trash.',
            ! $page->template()->hasUrl() => 'That page has no URL to show.',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['page' => $problem]);
        }
    }
}
