<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\PagePassword\PagePasswordSchema;

/**
 * Creates the page_password field. Safe to run again; page_password.enable_template runs it.
 */
final class InstallPagePassword extends Action
{
    public static function name(): string
    {
        return 'page_password.install';
    }

    public static function description(): string
    {
        return 'Install password-protected pages: creates the page_password field. Safe to run again; page_password_enable_template runs it.';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return bool whether the field was created */
    public function handle(Input $input, ActionContext $context): bool
    {
        $existing = lynx()->fields->find(PagePasswordSchema::FIELD);

        if ($existing !== null && ($existing->type !== PagePasswordSchema::TYPE || $existing->isTranslatable())) {
            throw ValidationException::withMessages(['fields' => 'Field ['.PagePasswordSchema::FIELD.'] exists with other settings; page passwords need a shared field of type ['.PagePasswordSchema::TYPE.'].']);
        }

        if ($existing !== null) {
            return false;
        }

        $context->run(CreateField::class, ['name' => PagePasswordSchema::FIELD, 'type' => PagePasswordSchema::TYPE, 'label' => PagePasswordSchema::LABEL]);

        return true;
    }
}
