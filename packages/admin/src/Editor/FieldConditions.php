<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FieldCondition;
use LynxCms\Core\Templates\TemplateField;

/**
 * show_if and required_if for the editor's form (ADR-0030), evaluated with core's FieldCondition
 * against the form as it is now, so the browser and pages.save never disagree. Conditions read
 * the shared value or the default language's.
 *
 * @internal
 */
final readonly class FieldConditions
{
    public function __construct(private FormValues $forms) {}

    /**
     * @param  array<string, TemplateField>  $fields  every field of the template
     * @param  array<array-key, mixed>  $form  field name => language code => form value
     * @return array<string, array{shown: bool, required: bool}>
     */
    public function states(array $fields, array $form): array
    {
        $default = Value::string(lynx()->languages->default()->code);
        $valueOf = function (string $name) use ($fields, $form, $default): mixed {
            $type = isset($fields[$name]) ? (string) $fields[$name]->field->type : '';

            return $this->forms->fromForm($type, Value::array($form[$name] ?? [])[$default] ?? null);
        };
        $states = [];

        foreach ($fields as $name => $templateField) {
            $states[$name] = ['shown' => $templateField->isShown($valueOf), 'required' => $templateField->isRequired($valueOf)];
        }

        return $states;
    }

    /**
     * @param  array<string, TemplateField>  $fields
     * @return list<string> the fields some condition reads, whose inputs update the form as you type
     */
    public function watched(array $fields): array
    {
        $watched = [];

        foreach ($fields as $templateField) {
            foreach ([$templateField->showIf(), $templateField->requiredIf()] as $condition) {
                array_push($watched, ...($condition instanceof FieldCondition ? $condition->fields() : []));
            }
        }

        return array_values(array_unique($watched));
    }
}
