<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Templates;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\SectionCopy;
use LynxCms\Core\Templates\TemplateRegistry;
use Override;

/**
 * Copies a template's section, with its fields and their settings, into other templates (ADR-0108).
 * The copies are not linked: each template owns its section afterwards. A template that already
 * has one of the fields is refused, or, with rename_conflicts, gets a new field cloned from it.
 * Returns the names of the templates the section was copied to.
 */
final class CopySection extends Action
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly FieldRegistry $fields,
    ) {}

    public static function name(): string
    {
        return 'templates.copy-section';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    #[Override]
    public static function changesSchema(): bool
    {
        return true;
    }

    public static function description(): string
    {
        return 'Copy a section of a template, with its fields and their settings, into other templates. A template that already has one of the fields is refused, unless rename_conflicts is true: then a new field named prefix + name is cloned from it (prefix defaults to the section key and "_"). Values are not copied.';
    }

    public static function rules(): array
    {
        return [
            'template' => ['required', 'string', Rule::exists(Tables::TEMPLATES, 'name')],
            'section' => ['required', 'string'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['string', 'distinct', 'different:template', Rule::exists(Tables::TEMPLATES, 'name')],
            'rename_conflicts' => ['sometimes', 'boolean'],
            'prefix' => ['sometimes', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'template' => $schema->string()->description('The template that has the section.')->required(),
            'section' => $schema->string()->description('The section key.')->required(),
            'to' => $schema->array()->items($schema->string())->description('Templates to copy the section into.')->required(),
            'rename_conflicts' => $schema->boolean()->description('Clone a field a template already has under prefix + name, instead of refusing.'),
            'prefix' => $schema->string()->description('Prefix for renamed fields, e.g. "billing_". Defaults to the section key and "_".'),
        ];
    }

    /**
     * @return list<string>
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $source = $this->templates->get((string) $input->string('template'));
        $copy = SectionCopy::of($source, (string) $input->string('section'), $this->templates);
        $prefix = $input->string('prefix') ?? str_replace('-', '_', $copy->key()).'_';
        $renames = [];

        foreach (Value::strings($input->array('to')) as $name) {
            $renames[$name] = $this->renamesFor($this->templates->get($name), $copy, $input->bool('rename_conflicts'), $prefix);
        }

        $created = $this->createClones($renames, $context);

        foreach ($renames as $name => $names) {
            $this->copyInto($this->templates->get($name), $copy, $names, $context);
        }

        $context->changes->schema('template', "Copy section {$copy->key()} from {$source->name} to ".implode(', ', array_keys($renames))
            .($created !== [] ? '; create '.implode(', ', $created) : ''), [
                'template' => $source->name,
                'section' => $copy->key(),
                'to' => array_keys($renames),
                'created' => $created,
            ]);

        return array_keys($renames);
    }

    /**
     * @return array<string, string> the target's clashing fields => their new names
     */
    private function renamesFor(Template $target, SectionCopy $copy, bool $rename, string $prefix): array
    {
        $sections = array_column(Value::list($target->config()['sections'] ?? []), 'key');

        if (in_array($copy->key(), $sections, true)) {
            throw ValidationException::withMessages(['to' => "Template [{$target->name}] already has a section [{$copy->key()}]."]);
        }

        $clashes = array_values(array_intersect($copy->fieldNames(), $this->templates->fields($target)->keys()->all()));

        if ($clashes !== [] && ! $rename) {
            throw ValidationException::withMessages(['to' => "Template [{$target->name}] already has [".implode(', ', $clashes).']. Rename the fields that clash, or remove them from that template first.']);
        }

        return array_combine($clashes, array_map(fn (string $name): string => $prefix.$name, $clashes));
    }

    /**
     * Each renamed field once, cloned from the field it replaces.
     *
     * @param  array<string, array<string, string>>  $renames
     * @return list<string> the new fields' names
     */
    private function createClones(array $renames, ActionContext $context): array
    {
        $clones = [];

        foreach ($renames as $names) {
            foreach ($names as $old => $new) {
                $clones[$new] = $old;
            }
        }

        foreach ($clones as $new => $old) {
            if ($this->fields->find($new) !== null) {
                throw ValidationException::withMessages(['prefix' => "Field [{$new}] already exists: choose another prefix."]);
            }

            $original = $this->fields->get($old);
            $context->run(CreateField::class, [
                'name' => $new,
                'type' => $original->type,
                'label' => $original->labels()->toStorage(),
                'translatable' => $original->isTranslatable(),
                'settings' => $original->config(),
            ]);
        }

        return array_keys($clones);
    }

    /**
     * @param  array<string, string>  $names
     */
    private function copyInto(Template $target, SectionCopy $copy, array $names, ActionContext $context): void
    {
        $tabs = Value::list($target->config()['tabs'] ?? []);
        $input = ['template' => $target->name, 'add' => [$copy->entry($names)]];

        if ($copy->tab !== null && ! in_array($copy->tab['key'], array_column($tabs, 'key'), true)) {
            $input['tabs'] = [...$tabs, $copy->tab];
        }

        $context->run(UpdateTemplate::class, $input);
    }
}
