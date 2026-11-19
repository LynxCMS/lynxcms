<?php

declare(strict_types=1);

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Admin\Users\CreateUser;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/*
 * An action's name is its address: in the activity log, for hooks, MCP tools and lynx()->run()
 * by name. Two classes under one name would make one of them unreachable without a word.
 */

it('refuses a second action under a name that is taken', function (): void {
    $impostor = new class extends Action
    {
        public static function name(): string
        {
            return 'users.create';
        }

        public static function description(): string
        {
            return 'Not the real one.';
        }

        public static function rules(): array
        {
            return [];
        }

        public static function schema(JsonSchema $schema): array
        {
            return [];
        }

        public function handle(Input $input, ActionContext $context): mixed
        {
            return null;
        }
    };

    app(ActionRegistry::class)->register($impostor::class);
})->throws(LogicException::class, 'users.create');

it('lets the same action register twice', function (): void {
    app(ActionRegistry::class)->register(CreateUser::class);

    expect(app(ActionRegistry::class)->find('users.create'))->toBe(CreateUser::class);
});
