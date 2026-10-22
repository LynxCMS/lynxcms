<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Templates;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\FieldListInput;
use LynxCms\Core\Templates\Layout\LayoutRules;
use LynxCms\Core\Templates\TemplateFieldsWriter;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Creates a template, optionally copying another template's fields and settings ("from").
 */
final class CreateTemplate extends Action
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly TemplateFieldsWriter $writer,
    ) {}

    public static function name(): string
    {
        return 'templates.create';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Create a template with an ordered list of existing fields. Use "from" to copy another template\'s fields and settings; fields listed in "fields" are added after the copied ones. Each field entry is a name or {"name", "required", "label", "show_if", "required_if", "searchable"}.';
    }

    public static function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid', Rule::unique(Tables::TEMPLATES, 'uuid')],
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/', Rule::unique(Tables::TEMPLATES, 'name')],
            'label' => ['nullable', ...LocalizedText::rules()],
            'from' => ['nullable', 'string'],
            'fields' => ['sometimes', 'array'],
            'has_url' => ['sometimes', 'boolean'],
            'singleton' => ['sometimes', 'boolean'],
            'allowed_templates' => ['sometimes', 'array'],
            'allowed_templates.*' => ['string'],
            'allow_children' => ['sometimes', 'boolean'],
            'tabs' => ['sometimes', 'array'],
            'child_templates' => ['sometimes', 'array'],
            'child_templates.*' => ['string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/'],
            'parent_templates' => ['sometimes', 'array'],
            'parent_templates.*' => ['string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Machine name, e.g. "consulting".')->required(),
            'label' => $schema->string()->description('Name shown to editors: text, or an object of language code => text.'),
            'from' => $schema->string()->description('Name of a template whose fields and settings are copied first.'),
            'fields' => $schema->array()->description('Fields in order: names, or objects {"name", "required", "label", "show_if", "required_if", "searchable"}. show_if and required_if are conditions such as "format=onsite|hybrid, price>0" (ADR-0030).'),
            'has_url' => $schema->boolean()->description('False for settings pages and reusable content that must never be served. Default true.'),
            'singleton' => $schema->boolean()->description('Only one page may use this template (e.g. global settings).'),
            'allowed_templates' => $schema->array()->items($schema->string())->description('Templates pages may switch to.'),
            'allow_children' => $schema->boolean()->description('Whether pages using this template may have children. Default true.'),
            'child_templates' => $schema->array()->items($schema->string())->description('Templates children may use. Empty means any.'),
            'parent_templates' => $schema->array()->items($schema->string())->description('Templates the parent may use. Empty means any.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Template
    {
        $fields = [];
        $settings = [];

        if (($from = $input->string('from')) !== null) {
            $source = $this->templates->get($from);
            $settings = $source->config();

            foreach ($this->templates->fields($source) as $name => $templateField) {
                $fields[$name] = $templateField->settings;
            }
        }

        $list = FieldListInput::parse($input->array('fields'));
        $fields = array_merge($fields, $this->writer->normalize($list->fields));

        if ($list->sections !== []) {
            $settings['sections'] = [...Value::list($settings['sections'] ?? []), ...$list->sections];
        }

        foreach (Template::SETTING_KEYS as $key) {
            if ($input->has($key)) {
                $settings[$key] = $input->get($key);
            }
        }

        LayoutRules::assert($fields, $settings);

        $template = Template::query()->create([
            'uuid' => $input->string('uuid') ?? (string) Str::uuid(),
            'name' => $input->string('name'),
            'label' => $input->get('label') ?? Str::headline((string) $input->string('name')),
            'settings' => $settings ?: null,
        ]);

        $this->writer->write($template, $fields);
        $this->templates->flush();

        $context->changes->schema('template', "Create template {$template->name} with fields: ".(implode(', ', array_keys($fields)) ?: 'none'), [
            'name' => $template->name,
            'from' => $from,
            'fields' => $fields,
            'settings' => $settings,
        ]);

        return $template;
    }
}
