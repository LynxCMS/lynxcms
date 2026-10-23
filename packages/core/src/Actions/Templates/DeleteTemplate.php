<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Templates;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\TemplateUsage;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Deletes a template that no page uses.
 */
final class DeleteTemplate extends Action
{
    public function __construct(private readonly TemplateRegistry $templates, private readonly TemplateUsage $usage) {}

    public static function name(): string
    {
        return 'templates.delete';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Delete a template. Refused while any page uses it, trashed pages included: the refusal names them; switch them to another template (pages.switch_template) or delete them first.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['template' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['template' => $schema->string()->description('Name of the template.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Template
    {
        $template = $this->templates->get((string) $input->string('template'));
        if ($this->usage->count($template) > 0) {
            throw ValidationException::withMessages(['template' => $this->usage->refusal($template)]);
        }

        Template::query()->whereKey($template->id)->delete();
        $this->templates->flush();
        $context->changes->schema('template', "Delete template {$template->name}", ['name' => $template->name], destructive: true);

        return $template;
    }
}
