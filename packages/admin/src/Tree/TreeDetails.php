<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * What the page tree's Details view shows beside a title: the page's state in each language, its
 * status, and its last change. One instance per tree, so each person's name is looked up once.
 *
 * @internal
 */
final readonly class TreeDetails
{
    /** The page has its own title in the language. */
    public const string OWN = 'own';

    /** The page is there in the language, but its title comes from another one through the fallback. */
    public const string FOLLOWS = 'follows';

    /** The page is not published in the language. */
    public const string MISSING = 'missing';

    public function __construct(private ActorNames $names = new ActorNames) {}

    /**
     * @return array{languages: list<array{code: string, label: string, state: string}>, status: string, changed: ?string, changedAt: ?string, changedBy: ?string}
     */
    public function for(Page $page): array
    {
        $changedAt = $page->updatedAt === null ? null : now()->parse($page->updatedAt);

        return [
            'languages' => $this->languages($page),
            'status' => $this->status($page),
            'changed' => $changedAt?->diffForHumans(),
            'changedAt' => $changedAt?->toDayDateTimeString(),
            'changedBy' => $this->changedBy($page->modifiedBy),
        ];
    }

    /**
     * Empty on a site with one language, where languages are never mentioned.
     *
     * @return list<array{code: string, label: string, state: string}>
     */
    private function languages(Page $page): array
    {
        if (! lynx()->languages->isMultilingual()) {
            return [];
        }

        $hasTitle = $page->templateField('title') instanceof TemplateField;
        $languages = [];

        foreach (lynx()->languages->all() as $language) {
            $state = match (true) {
                ! $language->isDefault() && ! $page->isActiveIn($language) => self::MISSING,
                $hasTitle && Value::string($page->getLanguageValue($language, 'title')) === '' => self::FOLLOWS,
                default => self::OWN,
            };
            $languages[] = ['code' => Value::string($language->code), 'label' => $language->tabLabel(), 'state' => $state];
        }

        return $languages;
    }

    /** Unpublished outranks hidden: a hidden page is still live for anyone with its link. */
    private function status(Page $page): string
    {
        return match (true) {
            $page->hasStatus(PageStatus::UNPUBLISHED) => 'unpublished',
            $page->hasStatus(PageStatus::HIDDEN) => 'hidden',
            default => 'published',
        };
    }

    /** Who saved the page last, in words, from the stored actor ("user:1", "cli"). */
    private function changedBy(?string $actor): ?string
    {
        if ($actor === null || $actor === '') {
            return null;
        }

        $parts = explode(':', $actor, 2);

        return $this->names->label($parts[0], $parts[1] ?? null);
    }
}
