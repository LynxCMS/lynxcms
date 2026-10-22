<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Authorizer;

/**
 * Passed to every action and hook: who is acting, whether this is a dry run, and the change recorder.
 *
 * @api
 */
final readonly class ActionContext
{
    public function __construct(
        public string $action,
        public Actor $actor,
        public bool $dryRun,
        public int $depth,
        public ChangeSet $changes,
        private ActionRunner $runner,
    ) {}

    public function isNested(): bool
    {
        return $this->depth > 1;
    }

    /**
     * Run another action inside this one: same transaction, same change set, same dry-run mode.
     *
     * @param  class-string  $action
     * @param  array<string, mixed>|Input  $input
     */
    public function run(string $action, array|Input $input = []): mixed
    {
        return $this->runner->run($action, $input)->value;
    }

    /**
     * Check a permission for a page or template (ADR-0033); a refusal throws. Actions run inside
     * another action are not checked again: the outer action was.
     *
     * @param  Page|null  $page  the page acted on; for a new page, its parent
     * @param  Template|null  $template  the template to check, when not the page's own
     */
    public function authorize(string $permission, ?Page $page = null, ?Template $template = null): void
    {
        if (! $this->isNested()) {
            app(Authorizer::class)->authorize($this->actor, $permission, $page, $template);
        }
    }

    /**
     * Like authorize() for many pages at once, such as a branch about to be deleted.
     *
     * @param  list<int>  $pageIds
     */
    public function authorizePages(string $permission, array $pageIds): void
    {
        if (! $this->isNested()) {
            app(Authorizer::class)->authorizePages($this->actor, $permission, $pageIds);
        }
    }

    /**
     * Queue a domain event; it is dispatched only after the outer transaction commits.
     */
    public function event(object $event): void
    {
        $this->runner->queueEvent($event);
    }
}
