<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormSchema;

/**
 * Installs the form builder (ADR-0053): its fields, templates and the hidden page that holds the
 * forms. Runs the first time a form is created; safe to run again.
 */
final class InstallForms extends Action
{
    public static function name(): string
    {
        return 'forms.install';
    }

    public static function description(): string
    {
        return 'Install the form builder: its fields, templates and the hidden page that holds the forms. Safe to run again; forms_create runs it.';
    }

    public static function permission(): string
    {
        return 'forms.manage';
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

        foreach (FormSchema::templates() as $name => [$label, $fields, $singleton, $family]) {
            if (lynx()->templates->find($name) === null) {
                $context->run(CreateTemplate::class, ['name' => $name, 'label' => $label, 'fields' => $fields, 'has_url' => false, 'singleton' => $singleton] + $family);
                $installed = true;

                continue;
            }

            // Sites from an earlier version get the fields added since (form_once_per_user).
            $names = array_map(fn (string|array $field): string => is_array($field) ? Value::string($field['name'] ?? '') : $field, $fields);
            $missing = array_values(array_diff($names, lynx()->templates->fields($name)->keys()->all()));

            if ($missing !== []) {
                $context->run(UpdateTemplate::class, ['template' => $name, 'add' => $missing]);
                $installed = true;
            }
        }

        if (lynx()->pages->findOne('template='.FormSchema::ROOT_TEMPLATE.', include=all') === null) {
            $context->run(SavePage::class, [
                'template' => FormSchema::ROOT_TEMPLATE,
                'parent' => '/',
                'names' => [(string) lynx()->languages->default()->code => FormSchema::ROOT_NAME],
                'values' => ['title' => [(string) lynx()->languages->default()->code => 'Forms']],
                'status' => ['hidden', 'system'],
            ]);
            $installed = true;
        }

        return $installed;
    }

    private function installFields(ActionContext $context): bool
    {
        $installed = false;

        foreach (FormSchema::fields() as $name => [$type, $translatable, $label, $settings]) {
            $existing = lynx()->fields->find($name);

            if ($existing !== null && ($existing->type !== $type || $existing->isTranslatable() !== $translatable)) {
                throw ValidationException::withMessages(['fields' => "Field [{$name}] exists with other settings; the form builder needs [{$type}".($translatable ? ', translatable' : '').'].']);
            }

            if ($existing === null) {
                $context->run(CreateField::class, ['name' => $name, 'type' => $type, 'translatable' => $translatable, 'label' => $label, 'settings' => $settings]);
                $installed = true;
            } elseif ($existing->label !== $label || ($existing->config()['description'] ?? null) !== ($settings['description'] ?? null)) {
                // The builder's own fields are read only in the admin: newer wording (label, help) comes from here.
                $context->run(UpdateField::class, ['field' => $name, 'label' => $label, 'settings' => ['description' => $settings['description'] ?? null]]);
                $installed = true;
            }
        }

        return $installed;
    }
}
