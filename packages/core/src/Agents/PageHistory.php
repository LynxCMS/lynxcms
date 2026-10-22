<?php

declare(strict_types=1);

namespace LynxCms\Core\Agents;

use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\OldPath;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;

/**
 * The get_page_history read tool, shared by the MCP server and the built-in agent: a page's old
 * URLs and whether each still redirects, every page that references it (menu items included, so an
 * agent knows before renaming or deleting), its latest activity and revisions, and whether it has
 * a draft. What the admin's Settings tab shows, as data.
 *
 * @api
 */
final readonly class PageHistory
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @return array<string, mixed>|null null when no page matches the reference
     */
    public function for(string $reference, int $activityLimit = 20): ?array
    {
        $page = lynx()->pages->get($reference);

        if ($page === null) {
            return null;
        }

        app(Authorizer::class)->authorize(app(ActionRunner::class)->actor(), 'pages.view', $page);

        return [
            'page' => ['id' => $page->id, 'path' => $this->url($page)],
            'redirects' => array_map(fn (OldPath $old): array => [
                'language' => $old->language->code,
                'url' => $old->url(),
                'redirects' => $old->isRedirecting,
            ], lynx()->pages->redirects($page)),
            'linked_from' => lynx()->pages->backlinks($page)->map(fn (Page $linking): array => [
                'id' => $linking->id,
                'path' => $this->url($linking),
                'title' => Value::string($linking->get('title')),
                'template' => $linking->template()->name,
            ]),
            'activity' => array_map($this->entry(...), lynx()->pages->activity($page, $activityLimit)),
            // Saved versions of the values (ADR-0038), newest first: pages_restore_revision takes an id.
            'revisions' => array_map(fn (array $revision): array => [
                'id' => $revision['id'],
                'revision' => $revision['revision'],
                'by' => $revision['created_by'],
                'at' => $revision['created_at']->toIso8601String(),
            ], array_slice(lynx()->pages->revisions($page), 0, $activityLimit)),
            'has_draft' => lynx()->pages->draft($page) !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ActivityEvent $event): array
    {
        return [
            'action' => $event->action,
            'summary' => Value::string($event->changes[0]['summary'] ?? ''),
            'changes' => count($event->changes),
            'actor' => ['kind' => $event->actor_kind, 'id' => $event->actor_id],
            'at' => $event->created_at->toIso8601String(),
        ];
    }

    /** Pages without a URL (menu items, settings) report null. */
    private function url(Page $page): ?string
    {
        return $page->template()->hasUrl() ? $page->localUrl($this->languages->default()) : null;
    }
}
