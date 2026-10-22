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
            } elseif ($existing->label !== $label) {
                // The builder's own fields are read only in the admin: newer wording comes from here.
                $context->run(UpdateField::class, ['field' => $name, 'label' => $label]);
                $installed = true;
            }
        }

        return $installed;
    }
}
