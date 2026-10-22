<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Templates;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Deletes a template that no page uses.
 */
final class DeleteTemplate extends Action
{
    public function __construct(private readonly TemplateRegistry $templates) {}

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
        return 'Delete a template. Refused while any page uses it.';
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
        $pages = DB::table(Tables::PAGES)->where('template_id', $template->id)->whereNull('draft_of')->count();

        if ($pages > 0) {
            throw ValidationException::withMessages(['template' => "Template [{$template->name}] is used by {$pages} page(s)."]);
        }

        Template::query()->whereKey($template->id)->delete();
        $this->templates->flush();
        $context->changes->schema('template', "Delete template {$template->name}", ['name' => $template->name], destructive: true);

        return $template;
    }
}
