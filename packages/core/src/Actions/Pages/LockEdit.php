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

/**
 * Takes or renews the edit lock on a page (ADR-0037), so nobody else saves it while it is open
 * in an editor. Refused while someone else holds a lock that has not expired.
 */
final class LockEdit extends Action
{
    use ResolvesPages;

    public function __construct(private readonly EditLocks $locks) {}

    public static function name(): string
    {
        return 'pages.lock_edit';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Take or renew the edit lock on a page while editing it.';
    }

    /** An editor screen concern; saves tell agents when a page is being edited. */
    public static function exposedAsTool(): bool
    {
        return false;
    }

    /** Renewed every minute: not worth an activity entry each time. */
    public static function logsActivity(): bool
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

    /**
     * @return array{holder: string, acquired_at: string, expires_at: string}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = $this->resolvePageReference($input->get('page'));
        $context->authorize('pages.edit', $page);
        $current = $this->locks->current((int) $page->id);

        if ($current !== null && $current['holder'] !== EditLocks::holder($context->actor)) {
            throw ValidationException::withMessages(['page' => "Page [{$page->name()}] is being edited by {$current['holder']} until {$current['expires_at']->format('H:i')}."]);
        }

        $lock = $this->locks->acquire($context->actor, (int) $page->id);

        return ['holder' => $lock['holder'], 'acquired_at' => $lock['acquired_at']->toIso8601String(), 'expires_at' => $lock['expires_at']->toIso8601String()];
    }
}
