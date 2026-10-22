<?php

declare(strict_types=1);

namespace LynxCms\Seo\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Seo\SeoSchema;

/**
 * Creates the SEO fields, the settings template and its hidden system page (ADR-0055).
 * Safe to run again: anything that exists is left alone, and fields added since are added.
 */
final class InstallSeo extends Action
{
    public static function name(): string
    {
        return 'seo.install';
    }

    public static function description(): string
    {
        return 'Install the SEO module: its fields, the settings template and the hidden page that holds the site-wide SEO settings. Safe to run again; seo_enable_template runs it.';
    }

    public static function permission(): string
    {
        return 'seo.manage';
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
        $installed = $this->installFields($context);
        $installed = $this->installTemplate($context) || $installed;

        if (lynx()->pages->findOne('template='.SeoSchema::SETTINGS_TEMPLATE.', include=all') === null) {
            $language = (string) lynx()->languages->default()->code;
            $context->run(SavePage::class, [
                'template' => SeoSchema::SETTINGS_TEMPLATE,
                'parent' => '/',
                'names' => [$language => SeoSchema::SETTINGS_NAME],
                'values' => ['title' => [$language => 'SEO']],
                'status' => ['hidden', 'system'],
            ]);
            $installed = true;
        }

        return $installed;
    }

    private function installFields(ActionContext $context): bool
    {
        $installed = false;

        foreach (SeoSchema::fields() as $name => [$type, $translatable, $label, $settings]) {
            $existing = lynx()->fields->find($name);

            if ($existing !== null && ($existing->type !== $type || $existing->isTranslatable() !== $translatable)) {
                $needs = $type.($translatable ? ', translatable' : ', not translatable');

                throw ValidationException::withMessages(['fields' => "Field [{$name}] exists with other settings; the SEO module needs [{$needs}]."]);
            }

            if ($existing === null) {
                $context->run(CreateField::class, ['name' => $name, 'type' => $type, 'translatable' => $translatable, 'label' => $label, 'settings' => $settings]);
                $installed = true;
            }
        }

        return $installed;
    }

    /** A settings template from an earlier version gets the fields added since. */
    private function installTemplate(ActionContext $context): bool
    {
        if (lynx()->templates->find(SeoSchema::SETTINGS_TEMPLATE) === null) {
            $fields = ['title', ...SeoSchema::specs(SeoSchema::settingsFields())];
            $context->run(CreateTemplate::class, ['name' => SeoSchema::SETTINGS_TEMPLATE, 'label' => 'SEO settings', 'fields' => $fields, 'has_url' => false, 'singleton' => true]);

            return true;
        }

        $missing = array_values(array_diff(SeoSchema::settingsFields(), lynx()->templates->fields(SeoSchema::SETTINGS_TEMPLATE)->keys()->all()));

        if ($missing !== []) {
            $context->run(UpdateTemplate::class, ['template' => SeoSchema::SETTINGS_TEMPLATE, 'add' => SeoSchema::specs($missing)]);
        }

        return $missing !== [];
    }
}
