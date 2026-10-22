<?php

declare(strict_types=1);

namespace LynxCms\Cache\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Cache\PageCacheSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;

/**
 * Creates the page cache settings fields, template and hidden system page. Safe to run again.
 * The page cache works without it, from the config alone.
 */
final class InstallPageCache extends Action
{
    public static function name(): string
    {
        return 'page_cache.install';
    }

    public static function description(): string
    {
        return 'Install the page cache settings: the hidden page that holds the default lifetime and the template rules. Safe to run again.';
    }

    public static function permission(): string
    {
        return 'page_cache.manage';
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
        $fields = $this->installFields($context);
        $template = $this->installTemplate($context);
        $page = $this->installPage($context);

        return $fields || $template || $page;
    }

    private function installFields(ActionContext $context): bool
    {
        $installed = false;

        foreach (PageCacheSchema::fields() as $name => [$type, $label, $settings]) {
            $existing = lynx()->fields->find($name);

            if ($existing !== null && $existing->type !== $type) {
                throw ValidationException::withMessages(['fields' => "Field [{$name}] exists with another type; the page cache needs [{$type}]."]);
            }

            if ($existing === null) {
                $context->run(CreateField::class, ['name' => $name, 'type' => $type, 'label' => $label, 'settings' => $settings]);
                $installed = true;
            }
        }

        return $installed;
    }

    private function installTemplate(ActionContext $context): bool
    {
        if (lynx()->templates->find(PageCacheSchema::SETTINGS_TEMPLATE) !== null) {
            return false;
        }

        $fields = ['title'];

        foreach (PageCacheSchema::fields() as $name => $field) {
            $fields[] = ['name' => $name, 'description' => $field[3]];
        }

        $context->run(CreateTemplate::class, ['name' => PageCacheSchema::SETTINGS_TEMPLATE, 'label' => 'Page cache settings', 'fields' => $fields, 'has_url' => false, 'singleton' => true]);

        return true;
    }

    private function installPage(ActionContext $context): bool
    {
        if (lynx()->pages->findOne('template='.PageCacheSchema::SETTINGS_TEMPLATE.', include=all') !== null) {
            return false;
        }

        $language = (string) lynx()->languages->default()->code;
        $context->run(SavePage::class, [
            'template' => PageCacheSchema::SETTINGS_TEMPLATE,
            'parent' => '/',
            'names' => [$language => PageCacheSchema::SETTINGS_NAME],
            'values' => ['title' => [$language => 'Page cache']],
            'status' => ['hidden', 'system'],
        ]);

        return true;
    }
}
