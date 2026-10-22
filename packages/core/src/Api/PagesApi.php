<?php

declare(strict_types=1);

namespace LynxCms\Core\Api;

use InvalidArgumentException;
use LynxCms\Core\Actions\Pages\DeletePage;
use LynxCms\Core\Actions\Pages\MovePage;
use LynxCms\Core\Actions\Pages\PurgeOrphanedValues;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Pages\SortPage;
use LynxCms\Core\Actions\Pages\SwitchTemplate;
use LynxCms\Core\Actions\Pages\TranslatePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Api\Concerns\ManagesPageHistory;
use LynxCms\Core\Api\Concerns\TrashesPages;
use LynxCms\Core\Api\Concerns\VersionsPages;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\EditLocks;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageReferences;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Pages\ScheduleReader;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Query\Backlinks;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use UnexpectedValueException;

/**
 * $lynx->pages, modelled on ProcessWire's $pages.
 *
 * @api
 */
final readonly class PagesApi
{
    use ManagesPageHistory;
    use TrashesPages;
    use VersionsPages;

    public function __construct(
        private ActionRunner $runner,
        private PageLoader $loader,
        private PageFinder $finder,
        private PathResolver $paths,
        private LanguageRegistry $languages,
        private TemplateRegistry $templates,
        private Backlinks $backlinks,
        private PageReferences $references,
        private Trash $trash,
        private ScheduleReader $scheduleReader,
        private EditLocks $editLocks,
        private Revisions $revisionStore,
        private Drafts $drafts,
    ) {}

    /** A new, unsaved page. Set values and names, then save(). */
    public function new(string $template, Page|int|string|null $parent = '/'): Page
    {
        $parentId = $parent === null ? null : (int) ($this->get($parent)->id ?? throw new InvalidArgumentException('Parent page not found.'));

        return new Page($this->templates->get($template)->id, $parentId);
    }

    /**
     * Get one page, as ProcessWire's $pages->get() does (ADR-0029): by id (int or all digits), by
     * selector ("name=alpha, template=course"), by path in the default language ("/courses/delivery-8/"),
     * or by a bare page name: the first page with that name at any depth, whatever its status.
     */
    public function get(Page|int|string $reference): ?Page
    {
        if ($reference instanceof Page) {
            return $reference;
        }

        if (is_string($reference) && str_contains($reference, '=')) {
            return $this->finder->findOne($reference);
        }

        $id = is_int($reference) ? $reference : $this->references->id($reference);

        return $id === null ? null : $this->loader->find($id);
    }

    /**
     * Find pages. Pass $maxLimit when the selector comes from outside your code (a search box,
     * an API, an agent) so it cannot ask for every page at once.
     *
     * @param  list<string>  $with  page-reference fields to eager load
     * @param  PageScope|null  $scope  only pages these grants cover (Authorizer::scopeOf())
     */
    public function find(string $selector, array $with = [], ?int $maxLimit = null, ?int $defaultLimit = null, ?PageScope $scope = null): PageArray
    {
        return $this->finder->find($selector, $with, $maxLimit, $defaultLimit, $scope);
    }

    public function findOne(string $selector): ?Page
    {
        return $this->finder->findOne($selector);
    }

    public function count(string $selector): int
    {
        return $this->finder->count($selector);
    }

    /**
     * Save a page. With dryRun the page object is left untouched and the would-be changes are returned.
     *
     * @param  'human'|'machine'|'copied'  $origin
     */
    public function save(Page $page, bool $dryRun = false, string $origin = 'human', ?string $provider = null): Page|ActionResult
    {
        $input = ['page' => $dryRun ? clone $page : $page, 'origin' => $origin, 'provider' => $provider];

        return $this->runner->run(SavePage::class, $input, $dryRun)->valueOrDryRun(Page::class);
    }

    public function move(Page|int|string $page, Page|int|string $parent, ?int $sort = null, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(MovePage::class, array_filter([
            'page' => $this->ref($page),
            'parent' => $this->ref($parent),
            'sort' => $sort,
        ], fn (string|int|null $v): bool => $v !== null), $dryRun)->valueOrDryRun(Page::class);
    }

    /**
     * Number of children per parent id, in one query (parents without children get 0).
     * Hidden, unpublished and system children are excluded unless the selector includes them,
     * e.g. "include=all".
     *
     * @param  list<int>  $parentIds
     * @param  PageScope|null  $scope  count only the pages these grants cover (Authorizer::scopeOf())
     * @return array<int, int>
     */
    public function childCounts(array $parentIds, string $selector = '', ?PageScope $scope = null): array
    {
        return $this->finder->countByParent($parentIds, $selector, $scope);
    }

    /**
     * Add or remove editable status flags (hidden, unpublished, locked) without saving field values.
     *
     * @param  list<string>  $add
     * @param  list<string>  $remove
     */
    public function setStatus(Page|int|string $page, array $add = [], array $remove = [], bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(SetPageStatus::class, ['page' => $this->ref($page), 'add' => $add, 'remove' => $remove], $dryRun)
            ->valueOrDryRun(Page::class);
    }

    /**
     * Move a page to a 0-based position among its siblings (clamped to the end).
     */
    public function sort(Page|int|string $page, int $position, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(SortPage::class, ['page' => $this->ref($page), 'position' => $position], $dryRun)
            ->valueOrDryRun(Page::class);
    }

    /**
     * @return list<int>|ActionResult
     */
    public function delete(Page|int|string $page, bool $dryRun = false): array|ActionResult
    {
        $result = $this->runner->run(DeletePage::class, ['page' => $this->ref($page)], $dryRun);

        return $result->dryRun ? $result : Value::ints($result->arrayValue());
    }

    /**
     * @return array{page: Page, orphaned: list<string>}|ActionResult
     */
    public function switchTemplate(Page|int|string $page, string $template, bool $dryRun = false): array|ActionResult
    {
        $result = $this->runner->run(SwitchTemplate::class, ['page' => $this->ref($page), 'template' => $template], $dryRun);

        if ($result->dryRun) {
            return $result;
        }

        $value = $result->arrayValue();

        return [
            'page' => $value['page'] instanceof Page ? $value['page'] : throw new UnexpectedValueException('SwitchTemplate did not return the page.'),
            'orphaned' => Value::strings($value['orphaned'] ?? []),
        ];
    }

    /**
     * @return array<string, int>|ActionResult
     */
    public function purgeOrphans(Page|int|string|null $page = null, ?string $template = null, bool $dryRun = false): array|ActionResult
    {
        $result = $this->runner->run(PurgeOrphanedValues::class, array_filter([
            'page' => $page === null ? null : $this->ref($page),
            'template' => $template,
        ]), $dryRun);

        return $result->dryRun ? $result : array_map(Value::int(...), Value::map($result->arrayValue()));
    }

    /**
     * Machine-translate missing values into a language (needs a TranslationProvider, e.g. lynxcms/ai).
     *
     * @param  list<string>  $fields
     */
    public function translate(Page|int|string $page, string $to, ?string $from = null, array $fields = [], bool $overwrite = false, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(TranslatePage::class, array_filter([
            'page' => $this->ref($page),
            'to' => $to,
            'from' => $from,
            'fields' => $fields ?: null,
            'overwrite' => $overwrite ?: null,
        ], fn ($v): bool => $v !== null), $dryRun)->valueOrDryRun(Page::class);
    }

    private function ref(Page|int|string $page): int|string
    {
        return $page instanceof Page ? (int) $page->id : $page;
    }
}
