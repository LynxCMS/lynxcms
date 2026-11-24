<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Rules\FormName;

/**
 * Creates a form in the builder (ADR-0053): an unpublished page under the hidden forms page,
 * named by the form's key. Its fields are blocks of form_fields, saved with pages.save; publish
 * it once it has fields.
 *
 * @api
 */
final class CreateForm extends Action
{
    public function __construct(private readonly FormRegistry $forms) {}

    public static function name(): string
    {
        return 'forms.create';
    }

    public static function description(): string
    {
        return 'Create a form for the form builder. Then add its fields with pages_save: values.form_fields is a list of blocks {"type": "text"|"email"|"tel"|"textarea"|"number"|"date"|"select"|"radio"|"checkboxes"|"checkbox"|"file"|"group"|"step", "depth": 0, "values": {"form_label": {"<code>": "..."}, "form_required": true, "form_options": [{"value": "a", "label": {...}}], ...}}; blocks with depth 1 after a group are its fields. Publish it with pages_save status [].';
    }

    public static function permission(): string
    {
        return 'forms.manage';
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', new FormName],
            'label' => ['required'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The form\'s key, e.g. "newsletter": lowercase letters, digits and dashes.')->required(),
            'label' => $schema->string()->description('The form\'s title: text, or an object of language code => text.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $name = (string) $input->string('name');

        $isTaken = $this->forms->get($name) !== null
            || (lynx()->templates->find(FormSchema::FORM_TEMPLATE) !== null && lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name='.$name.', include=all') !== null);

        if ($isTaken) {
            throw ValidationException::withMessages(['name' => Value::string(__('lynx-forms::validation.form.taken', ['form' => $name]))]);
        }

        $context->run(InstallForms::class);
        $root = lynx()->pages->findOne('template='.FormSchema::ROOT_TEMPLATE.', include=all') ?? throw ValidationException::withMessages(['name' => Value::string(__('lynx-forms::validation.form.no_root'))]);
        $label = $input->get('label');
        $default = (string) lynx()->languages->default()->code;

        $page = $context->run(SavePage::class, [
            'template' => FormSchema::FORM_TEMPLATE,
            'parent' => $root->id,
            'names' => [$default => $name],
            'values' => ['title' => is_array($label) ? Value::map($label) : [$default => Value::string($label)]],
            'status' => ['unpublished'],
        ]);

        return $page instanceof Page ? $page : throw ValidationException::withMessages(['name' => Value::string(__('lynx-forms::validation.form.failed'))]);
    }
}
