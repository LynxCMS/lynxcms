<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Admin\AdminBranches;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;

/**
 * The filters on the page tree's Details view, each a selector (ADR-0090): pages with a draft,
 * pages with a schedule, and pages not published in each language besides the default. They cover
 * the site's pages in every status, without the trash, system pages and module branches (menus,
 * forms), which have screens of their own.
 *
 * @internal
 */
final readonly class TreeFilters
{
    public const string DRAFTS = 'drafts';

    public const string SCHEDULED = 'scheduled';

    /** Followed by a language code: "missing-pt". */
    public const string MISSING = 'missing-';

    private string $base;

    public function __construct(private ?PageScope $scope = null)
    {
        $this->base = $this->base();
    }

    /**
     * Each filter with its label and how many pages it finds.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    public function all(): array
    {
        $filters = [self::DRAFTS => 'Draft changes', self::SCHEDULED => 'Scheduled'];

        foreach ($this->otherLanguages() as $language) {
            $filters[self::MISSING.Value::string($language->code)] = 'Not in '.Value::string($language->name);
        }

        $all = [];

        foreach ($filters as $key => $label) {
            $all[] = ['key' => $key, 'label' => $label, 'count' => $this->count($key)];
        }

        return $all;
    }

    /** The whole selector for a filter, or null when there is no such filter. */
    public function selector(string $key): ?string
    {
        // Pages without a URL can have drafts and schedules too, but show in every language, so they
        // are never missing one (ADR-0096).
        $term = match (true) {
            $key === self::DRAFTS => 'has_draft=1, include=no_url',
            $key === self::SCHEDULED => 'has_schedule=1, include=no_url',
            str_starts_with($key, self::MISSING) => $this->missing(substr($key, strlen(self::MISSING))),
            default => null,
        };

        return $term === null ? null : "{$this->base}, {$term}";
    }

    private function count(string $key): int
    {
        return lynx()->pages->find($this->selector($key).', limit=1', scope: $this->scope)->total();
    }

    private function missing(string $code): ?string
    {
        foreach ($this->otherLanguages() as $language) {
            if ($language->code === $code) {
                return "language!={$code}";
            }
        }

        return null;
    }

    /** @return list<Language> */
    private function otherLanguages(): array
    {
        return array_values(lynx()->languages->all()->reject(fn (Language $language): bool => $language->isDefault())->all());
    }

    /** Every status but system; nothing under the trash or a module's branch. */
    private function base(): string
    {
        $outside = [lynx()->pages->trashId()];
        $roots = app(AdminBranches::class)->rootTemplates();

        if ($roots !== []) {
            $outside = [...$outside, ...lynx()->pages->find('template='.implode('|', $roots).', include=all')->ids()];
        }

        $outside = array_filter($outside);
        $selector = 'include=hidden, include=unpublished';
        $selector .= $roots === [] ? '' : ', template!='.implode('|', $roots);

        return $selector.($outside === [] ? '' : ', has_parent!='.implode('|', $outside));
    }
}
