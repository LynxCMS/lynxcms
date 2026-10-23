<?php

declare(strict_types=1);

namespace LynxCms\Seo\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\SeoSchema;

/**
 * Removes a template's structured data mapping: its pages keep the site, page and breadcrumb nodes
 * every page has.
 */
final class UnmapTemplate extends Action
{
    public static function name(): string
    {
        return 'seo.unmap_template';
    }

    public static function description(): string
    {
        return 'Remove a template\'s structured data (schema.org) mapping. Its pages keep the WebSite, WebPage and BreadcrumbList nodes every page has.';
    }

    public static function permission(): string
    {
        return 'seo.manage';
    }

    public static function rules(): array
    {
        return ['template' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['template' => $schema->string()->description('The template name, e.g. "course".')->required()];
    }

    /** @return bool whether a mapping was removed */
    public function handle(Input $input, ActionContext $context): bool
    {
        $template = $input->string('template') ?? '';
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
        $mappings = Mappings::items($settings);
        $kept = array_values(array_filter($mappings, fn (array $mapping): bool => $mapping['template'] !== $template));

        if (count($kept) === count($mappings)) {
            return false;
        }

        $context->changes->schema('seo', "Remove the structured data mapping of template [{$template}]", ['template' => $template]);
        $context->run(SavePage::class, ['id' => $settings?->id, 'values' => [SeoSchema::SCHEMAS => Mappings::toItems($kept)]]);

        return true;
    }
}
