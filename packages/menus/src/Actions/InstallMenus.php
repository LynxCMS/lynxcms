<?php

declare(strict_types=1);

namespace LynxCms\Menus\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * Creates the menu fields, templates and the hidden system page that holds every menu.
 * Safe to run again: anything that exists is left alone.
 */
final class InstallMenus extends Action
{
    public static function name(): string
    {
        return 'menus.install';
    }

    public static function description(): string
    {
        return 'Install the menu builder: its fields, templates and the hidden page that holds all menus. Safe to run again.';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $installed = $this->installFields($context);
        $installed = $this->installTemplates($context) || $installed;

        if (lynx()->pages->findOne('template='.MenuSchema::ROOT_TEMPLATE.', include=all') === null) {
            $context->run(SavePage::class, [
                'template' => MenuSchema::ROOT_TEMPLATE,
                'parent' => '/',
                'names' => [(string) lynx()->languages->default()->code => MenuSchema::ROOT_NAME],
                'values' => ['title' => [lynx()->languages->default()->code => 'Menus']],
                'status' => ['hidden', 'system'],
            ]);
            $installed = true;
        }

        return $installed;
    }

    private function installFields(ActionContext $context): bool
    {
        $installed = false;

        foreach (MenuSchema::fields() as $name => [$type, $translatable, $label, $settings]) {
            $existing = lynx()->fields->find($name);

            if ($existing !== null && ($existing->type !== $type || $existing->isTranslatable() !== $translatable)) {
                $needs = $type.($translatable ? ', translatable' : ', not translatable');

                throw ValidationException::withMessages(['fields' => "Field [{$name}] exists with other settings; the menu builder needs [{$needs}]."]);
            }

            if ($existing === null) {
                $context->run(CreateField::class, ['name' => $name, 'type' => $type, 'translatable' => $translatable, 'label' => $label, 'settings' => $settings]);
                $installed = true;
            }
        }

        return $installed;
    }

    /**
     * Templates from an earlier version get the fields added since.
     *
     * @param  list<string|array<string, mixed>>  $fields
     */
    private function addMissingFields(ActionContext $context, string $template, array $fields): bool
    {
        $names = array_map(fn (string|array $field): string => is_array($field) ? Value::string($field['name'] ?? '') : $field, $fields);
        $missing = array_values(array_diff($names, lynx()->templates->fields($template)->keys()->all()));

        if ($missing !== []) {
            $context->run(UpdateTemplate::class, ['template' => $template, 'add' => $missing]);
        }

        return $missing !== [];
    }

    private function installTemplates(ActionContext $context): bool
    {
        $installed = false;

        foreach (MenuSchema::templates() as $name => [$label, $fields, $singleton]) {
            if (lynx()->templates->find($name) !== null) {
                $installed = $this->addMissingFields($context, $name, $fields) || $installed;

                continue;
            }

            $context->run(CreateTemplate::class, ['name' => $name, 'label' => $label, 'fields' => $fields, 'has_url' => false, 'singleton' => $singleton]);
            $installed = true;
        }

        return $installed;
    }
}
