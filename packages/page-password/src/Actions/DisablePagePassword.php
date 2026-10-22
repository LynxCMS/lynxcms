<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\PagePassword\PagePasswordSchema;

/**
 * Takes the password field off a template. Passwords stay stored, but no longer protect pages,
 * until the field comes back.
 */
final class DisablePagePassword extends Action
{
    public static function name(): string
    {
        return 'page_password.disable_template';
    }

    public static function description(): string
    {
        return 'Remove the page_password field from a template. Its pages become public; the passwords are kept and apply again if the field is added back.';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function rules(): array
    {
        return ['template' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['template' => $schema->string()->description('The template name, e.g. "members".')->required()];
    }

    /** @return bool whether the template changed */
    public function handle(Input $input, ActionContext $context): bool
    {
        $template = $input->string('template') ?? '';

        if (lynx()->templates->find($template) === null) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] does not exist."]);
        }

        if (! lynx()->templates->fields($template)->has(PagePasswordSchema::FIELD)) {
            return false;
        }

        $context->run(UpdateTemplate::class, ['template' => $template, 'remove' => [PagePasswordSchema::FIELD]]);

        return true;
    }
}
