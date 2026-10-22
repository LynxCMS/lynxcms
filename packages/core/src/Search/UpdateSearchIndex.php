<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use LynxCms\Core\Events\LanguageCreated;
use LynxCms\Core\Events\LanguageDeleted;
use LynxCms\Core\Events\LanguageUpdated;
use LynxCms\Core\Events\PageDeleted;
use LynxCms\Core\Events\PageRestored;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Events\PageTemplateSwitched;
use LynxCms\Core\Events\TemplateUpdated;

/**
 * Keeps the search index current after the actions that change what pages say (ADR-0054).
 *
 * @internal
 */
final readonly class UpdateSearchIndex
{
    /** @var list<class-string> */
    public const array EVENTS = [PageSaved::class, PageRestored::class, PageTemplateSwitched::class, PageDeleted::class, TemplateUpdated::class, LanguageCreated::class, LanguageUpdated::class, LanguageDeleted::class];

    public function __construct(private SearchIndex $index) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof PageSaved, $event instanceof PageRestored, $event instanceof PageTemplateSwitched => $this->index->update($event->pageId),
            $event instanceof PageDeleted => array_map($this->index->remove(...), $event->pageIds),
            $event instanceof TemplateUpdated => $this->index->rebuild($event->templateId),
            $event instanceof LanguageCreated => $this->index->rebuild(),
            // Rows hold each language's text with fallback, so a new fallback changes them.
            $event instanceof LanguageUpdated && $event->changedFallback() => $this->index->rebuild(),
            $event instanceof LanguageDeleted => $this->index->removeLanguage($event->language->id),
            default => null,
        };
    }
}
