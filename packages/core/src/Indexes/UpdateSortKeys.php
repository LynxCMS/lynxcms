<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use LynxCms\Core\Events\LanguageCreated;
use LynxCms\Core\Events\LanguageDeleted;
use LynxCms\Core\Events\LanguageUpdated;
use LynxCms\Core\Events\PageDeleted;
use LynxCms\Core\Events\PageRestored;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Events\PageTemplateSwitched;
use LynxCms\Core\Events\TemplateUpdated;

/**
 * Keeps the sort keys current after the actions that change what pages show (ADR-0061). A
 * language added, removed or falling back differently changes keys of every page.
 *
 * @internal
 */
final readonly class UpdateSortKeys
{
    /** @var list<class-string> */
    public const array EVENTS = [PageSaved::class, PageRestored::class, PageTemplateSwitched::class, PageDeleted::class, TemplateUpdated::class, LanguageCreated::class, LanguageUpdated::class, LanguageDeleted::class];

    public function __construct(private SortKeys $keys) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof PageSaved, $event instanceof PageRestored, $event instanceof PageTemplateSwitched => $this->keys->update($event->pageId),
            $event instanceof PageDeleted => $this->keys->remove($event->pageIds),
            $event instanceof TemplateUpdated => $this->keys->rebuild($event->templateId),
            $event instanceof LanguageCreated, $event instanceof LanguageDeleted => $this->keys->rebuild(),
            $event instanceof LanguageUpdated && $event->changedFallback() => $this->keys->rebuild(),
            default => null,
        };
    }
}
