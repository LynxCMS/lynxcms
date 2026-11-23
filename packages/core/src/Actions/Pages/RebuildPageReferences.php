<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\References\PageReferenceIndex;

/**
 * Compares the page references index with every page's stored values and fixes the rows that
 * differ (ADR-0145). Saves keep the index current; run this after upgrading or after writing field
 * tables outside Lynx's actions. Needs page_references.manage.
 *
 * @api
 */
final class RebuildPageReferences extends Action
{
    public function __construct(private readonly PageReferenceIndex $index) {}

    public static function name(): string
    {
        return 'page_references.rebuild';
    }

    public static function permission(): string
    {
        return 'page_references.manage';
    }

    public static function description(): string
    {
        return 'Rebuild the index of page references that backlinks read: compare every page\'s stored values with the index and fix the rows that differ. Saving a page already updates its rows; use a dry run to list how many differ.';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array{pages: int, added: int, removed: int, resorted: int}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $totals = $this->index->rebuild();

        $context->changes->updated('page_references', null, "Rebuild page references: {$totals['pages']} page(s) differed; {$totals['added']} added, {$totals['removed']} removed, {$totals['resorted']} reordered", $totals);

        return $totals;
    }
}
