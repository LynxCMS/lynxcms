<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\EditLocks;
use LynxCms\Core\Permissions\Authorizer;

/**
 * Releases the edit lock on a page (ADR-0037): the holder's own, or anyone's for a superuser
 * (someone left a page open and went home).
 */
final class UnlockEdit extends Action
{
    use ResolvesPages;

    public function __construct(private readonly EditLocks $locks, private readonly Authorizer $authorizer) {}

    public static function name(): string
    {
        return 'pages.unlock_edit';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Release the edit lock on a page: your own, or anyone\'s as a superuser.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['page' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('Id, default-language path or page name of the page.')->required()];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $page = $this->resolvePageReference($input->get('page'));
        $current = $this->locks->current((int) $page->id);

        if ($current === null) {
            return false;
        }

        if ($current['holder'] !== EditLocks::holder($context->actor) && ! $this->authorizer->isSuperuser($context->actor)) {
            throw ValidationException::withMessages(['page' => "The edit lock on [{$page->name()}] is held by {$current['holder']}; only they or a superuser may release it."]);
        }

        $this->locks->release((int) $page->id);
        $context->changes->updated('page', $page->id, "Release the edit lock on {$page->name()} (held by {$current['holder']})");

        return true;
    }
}
