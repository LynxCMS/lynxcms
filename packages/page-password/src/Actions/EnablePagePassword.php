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
 * Lets a template's pages have a password: adds the page_password field to the Content tab's
 * sidebar, installing first. Pages below a protected page share its password.
 */
final class EnablePagePassword extends Action
{
    public static function name(): string
    {
        return 'page_password.enable_template';
    }

    public static function description(): string
    {
        return 'Let pages of a template with URLs have a password visitors must type to open them and the pages below them: adds the page_password field to the template.';
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
        $definition = lynx()->templates->find($template);

        if ($definition === null) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] does not exist."]);
        }

        if (! $definition->hasUrl()) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] has no URL: visitors never open its pages."]);
        }

        $context->run(InstallPagePassword::class);

        if (lynx()->templates->fields($template)->has(PagePasswordSchema::FIELD)) {
            return false;
        }

        $context->run(UpdateTemplate::class, ['template' => $template, 'add' => [[
            'name' => PagePasswordSchema::FIELD,
            'column' => 'sidebar',
            'description' => PagePasswordSchema::DESCRIPTION,
        ]]]);

        return true;
    }
}
