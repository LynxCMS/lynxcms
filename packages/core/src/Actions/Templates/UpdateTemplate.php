<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Templates;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\TemplateUpdated;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\FieldListInput;
use LynxCms\Core\Templates\Layout\LayoutRules;
use LynxCms\Core\Templates\TemplateFieldsWriter;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Changes a template's label, flags or fields. Removing a field keeps its values as orphaned
 * rows (ADR-0012); only PurgeOrphanedValues deletes them.
 */
final class UpdateTemplate extends Action
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly TemplateFieldsWriter $writer,
    ) {}

    public static function name(): string
    {
        return 'templates.update';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Change a template: label, has_url, singleton, allowed_templates (templates pages may switch to), family rules (allow_children, child_templates, parent_templates), and its fields. Use "fields" to replace the whole ordered list, or "add" and "remove". Removed fields keep their values until purged.';
    }

    public static function rules(): array
    {
        return [
            'template' => ['required', 'string'],
            'label' => ['sometimes', ...LocalizedText::rules()],
            'fields' => ['sometimes', 'array'],
            'add' => ['sometimes', 'array'],
            'remove' => ['sometimes', 'array'],
            'remove.*' => ['string'],
            'has_url' => ['sometimes', 'boolean'],
            'singleton' => ['sometimes', 'boolean'],
            'allowed_templates' => ['sometimes', 'array'],
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
            'template' => $schema->string()->description('Name of the template.')->required(),
            'label' => $schema->string(),
            'fields' => $schema->array()->description('New complete ordered field list (names or objects {"name", "required", "label", "show_if", "required_if", "tab", "column", "searchable"}), with optional sections {"section": "hero", "label": "Hero", "tab", "column", "collapsed", "fields": [...]}. show_if and required_if are conditions such as "format=onsite|hybrid, price>0" (ADR-0030). column is "main" or "sidebar". searchable: true adds the field text to the search index (search*="words"). A page field\'s entry may also set "min", "max" and "templates" for this template, and a repeater\'s or matrix\'s "min" and "max" (ADR-0094).'),
            'tabs' => $schema->array()->description('Editor tabs besides Content: [{"key": "seo", "label": "SEO"}]. Fields and sections name them in "tab".'),
            'add' => $schema->array()->description('Fields to append (names or objects); existing entries get their settings updated.'),
            'remove' => $schema->array()->items($schema->string())->description('Field names to remove. Values are kept as orphans.'),
            'has_url' => $schema->boolean(),
            'singleton' => $schema->boolean(),
            'allowed_templates' => $schema->array()->items($schema->string())->description('Templates pages may switch to.'),
            'allow_children' => $schema->boolean()->description('Whether pages using this template may have children. Default true.'),
            'child_templates' => $schema->array()->items($schema->string())->description('Templates children may use. Empty means any.'),
            'parent_templates' => $schema->array()->items($schema->string())->description('Templates the parent may use. Empty means any.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Template
    {
        $template = Template::query()->findOrFail($this->templates->get((string) $input->string('template'))->id);
        $before = $this->templates->fields($template)->map(fn ($tf) => $tf->settings)->all();
        $fields = $before;

        $settings = $template->config();
        $sections = Value::list($settings['sections'] ?? []);

        if ($input->has('fields')) {
            $list = FieldListInput::parse($input->array('fields'));
            $fields = $this->writer->normalize($list->fields);
            $sections = $list->sections;
        }

        $added = FieldListInput::parse($input->array('add'));

        foreach ($this->writer->normalize($added->fields) as $name => $fieldSettings) {
            $fields[$name] = array_merge($fields[$name] ?? [], $fieldSettings);
        }

        $sections = [...$sections, ...$added->sections];

        foreach (Value::strings($input->array('remove')) as $name) {
            unset($fields[$name]);
        }

        foreach (Template::SETTING_KEYS as $key) {
            if ($input->has($key)) {
                $settings[$key] = $input->get($key);
            }
        }

        $settings['sections'] = $sections;
        $settings = array_filter($settings, fn (mixed $value, string $key): bool => ! in_array($key, ['sections', 'tabs'], true) || $value !== [], ARRAY_FILTER_USE_BOTH);
        LayoutRules::assert($fields, $settings);

        if ($input->has('label')) {
            $template->label = LocalizedText::from($input->get('label'))->toColumn();
        }

        $template->settings = $settings ?: null;
        $template->save();

        if ($fields !== $before) {
            $this->writer->write($template, $fields);
        }

        $this->templates->flush();
        $context->event(new TemplateUpdated($template->id));

        $removed = array_values(array_diff(array_keys($before), array_keys($fields)));
        $added = array_values(array_diff(array_keys($fields), array_keys($before)));
        $context->changes->schema('template', "Update template {$template->name}"
            .($added !== [] ? '; add '.implode(', ', $added) : '')
            .($removed !== [] ? '; remove '.implode(', ', $removed).' (values kept as orphans)' : ''), [
                'name' => $template->name,
                'added' => $added,
                'removed' => $removed,
                'fields' => array_keys($fields),
            ]);

        return $template;
    }
}
