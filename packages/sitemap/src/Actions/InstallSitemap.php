<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Sitemap\SitemapSchema;

/**
 * Creates the sitemap settings fields, template and hidden system page. Safe to run again. The
 * sitemap itself works without it, from the config alone.
 */
final class InstallSitemap extends Action
{
    public static function name(): string
    {
        return 'sitemap.install';
    }

    public static function description(): string
    {
        return 'Install the sitemap settings: the hidden page that holds the URLs per file, the grouping and the templates left out. Safe to run again.';
    }

    public static function permission(): string
    {
        return 'sitemap.manage';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return bool whether anything was installed */
    public function handle(Input $input, ActionContext $context): bool
    {
        $installed = false;

        foreach (SitemapSchema::fields() as $name => [$type, $label, $settings]) {
            $existing = lynx()->fields->find($name);

            if ($existing !== null && $existing->type !== $type) {
                throw ValidationException::withMessages(['fields' => "Field [{$name}] exists with another type; the sitemap needs [{$type}]."]);
            }

            if ($existing === null) {
                $context->run(CreateField::class, ['name' => $name, 'type' => $type, 'label' => $label, 'settings' => $settings]);
                $installed = true;
            }
        }

        if (lynx()->templates->find(SitemapSchema::SETTINGS_TEMPLATE) === null) {
            $fields = ['title', ...array_map(fn (string $name, array $field): array => ['name' => $name, 'description' => $field[3]], array_keys(SitemapSchema::fields()), SitemapSchema::fields())];
            $context->run(CreateTemplate::class, ['name' => SitemapSchema::SETTINGS_TEMPLATE, 'label' => 'Sitemap settings', 'fields' => $fields, 'has_url' => false, 'singleton' => true]);
            $installed = true;
        }

        if (lynx()->pages->findOne('template='.SitemapSchema::SETTINGS_TEMPLATE.', include=all') === null) {
            $language = (string) lynx()->languages->default()->code;
            $context->run(SavePage::class, [
                'template' => SitemapSchema::SETTINGS_TEMPLATE,
                'parent' => '/',
                'names' => [$language => SitemapSchema::SETTINGS_NAME],
                'values' => ['title' => [$language => 'Sitemap']],
                'status' => ['hidden', 'system'],
            ]);
            $installed = true;
        }

        return $installed;
    }
}
