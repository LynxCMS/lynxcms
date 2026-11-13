<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Schema;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Schema\Draft\DraftBuilder;
use LynxCms\Core\Schema\Draft\DraftCompiler;
use LynxCms\Core\Schema\Draft\DraftError;
use LynxCms\Core\Schema\Draft\DraftOptions;
use LynxCms\Core\Schema\Draft\DraftReader;

/**
 * Builds a draft of templates and fields (ADR-0116) for the MCP server and the agent: the database
 * schema only, never files, so an agent can't rewrite a site's schema files, views or types. The
 * agent runs it with dry_run first and shows the plan. A draft that leaves fields out of a
 * template the site has is refused unless prune_fields is given.
 */
final class BuildDraft extends Action
{
    public function __construct(
        private readonly DraftReader $reader,
        private readonly DraftCompiler $compiler,
        private readonly DraftBuilder $builder,
    ) {}

    public static function name(): string
    {
        return 'schema.draft';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Build templates and fields from a draft in the short YAML syntax of php artisan lynx:draft. Run it with dry_run first, show the person the plan, and build only once they agree. It adds and changes fields and templates, never deletes them, and writes no files (views, schema files): tell the developer to run lynx:schema:export or lynx:make:view if they want those. A template the site has gets the draft\'s field list: a draft that leaves fields out is refused, and prune_fields removes them, only once the person agrees.';
    }

    public static function rules(): array
    {
        return ['draft' => ['required', 'string'], 'prune_fields' => ['sometimes', 'boolean']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'draft' => $schema->string()->description(
                'The draft as YAML: {fields: {name: definition}, templates: {name: {label, url, singleton, children, parents, allowed, tabs, fields}}}. '
                .'A field definition is "type[:argument] tokens", e.g. "textarea translatable", "decimal:2 min:0", "choice:Small,Large multiple", "page:course multiple", "text label:\"Button text\"", '
                .'or an object {type, label, translatable, ...settings}; a repeater lists item fields as {type: repeater, fields: [name, other*]}. '
                .'Template fields are names ("title*" is required there), {name: type} to define a field in place, or {section: "Label", tab, fields: [...]}. Call describe_field_types for the types and their settings.'
            )->required(),
            'prune_fields' => $schema->boolean()->description('Remove the fields the draft leaves out of a template the site has. Without it, such a draft is refused.'),
        ];
    }

    /**
     * @return array{plan: list<string>}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $options = new DraftOptions(views: false, writeFiles: false, pruneFields: $input->bool('prune_fields'));

        try {
            $result = $this->builder->build($this->compiler->compile($this->reader->parse((string) $input->string('draft'))), $options);
        } catch (DraftError $e) {
            throw ValidationException::withMessages(['draft' => $e->getMessage()]);
        }

        if ($result->hasErrors()) {
            throw ValidationException::withMessages(['draft' => $result->sync->errors]);
        }

        return ['plan' => $result->plan->lines()];
    }
}
