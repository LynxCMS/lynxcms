<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Creates a draft's pages once its schema is built (ADR-0116): through pages.save, unpublished,
 * all in one transaction, so a page the save still refuses leaves none behind. A path the site
 * has is kept as it is.
 *
 * @internal
 */
final readonly class DraftPageWriter
{
    private const array STATUS = ['unpublished'];

    /**
     * @param  list<DraftPage>  $pages
     * @return list<array{action: string, kind: string, name: string}>
     */
    public function steps(array $pages): array
    {
        return array_map(fn (DraftPage $page): array => [
            'action' => $this->exists($page) ? DraftPlan::KEEP : DraftPlan::CREATE,
            'kind' => 'page',
            'name' => "{$page->path} ({$page->template})",
        ], $pages);
    }

    /**
     * @param  list<DraftPage>  $pages
     *
     * @throws DraftError when a save refuses a page
     */
    public function create(array $pages): void
    {
        DB::transaction(function () use ($pages): void {
            foreach ($pages as $page) {
                if (! $this->exists($page)) {
                    $this->save($page);
                }
            }
        });
    }

    private function save(DraftPage $page): void
    {
        $language = (string) lynx()->languages->default()->code;

        try {
            lynx()->run(SavePage::class, [
                'template' => $page->template,
                'parent' => $page->parent,
                'names' => [$language => $page->name],
                'language' => $language,
                'values' => $page->values,
                'status' => self::STATUS,
            ]);
        } catch (ValidationException $e) {
            throw new DraftError("Page [{$page->path}]: ".Value::string(collect($e->errors())->flatten()->first()).' The fields and templates are built; no page was created.', $e->getCode(), previous: $e);
        }
    }

    private function exists(DraftPage $page): bool
    {
        return lynx()->pages->get($page->path.'/') instanceof Page;
    }
}
