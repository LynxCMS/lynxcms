<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Search;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Search\SearchIndex;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Indexes pages for search again (ADR-0054): after upgrading, or after a field's settings change
 * what its values say (a choice label, a matrix type's fields). Saves keep the index current
 * otherwise.
 */
final class RebuildSearchIndex extends Action
{
    public function __construct(
        private readonly SearchIndex $index,
        private readonly TemplateRegistry $templates,
    ) {}

    public static function name(): string
    {
        return 'search.rebuild';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Rebuild the search index that search*="words" selectors use: every page, or only the pages of "template". Saving a page already updates its entry; run this after changing field settings.';
    }

    public static function rules(): array
    {
        return ['template' => ['nullable', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['template' => $schema->string()->description('Only pages using this template. Default: every page.')];
    }

    /**
     * @return array{pages: int}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $template = $input->string('template');
        $pages = $this->index->rebuild($template === null ? null : $this->templates->get($template)->id);

        $context->changes->updated('search', null, "Index {$pages} page(s) for search".($template === null ? '' : " using {$template}"), ['pages' => $pages]);

        return ['pages' => $pages];
    }
}
