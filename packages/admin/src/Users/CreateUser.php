<?php

declare(strict_types=1);

namespace LynxCms\Admin\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\ResetUserPassword;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Creates an account in the host app's users with a password and roles, as lynx:superuser does for
 * a site's first superuser (ADR-0079). Superusers only (the console is one), and never an agent
 * tool: passwords don't go through agents. The password is never recorded.
 */
final class CreateUser extends Action
{
    public function __construct(private readonly LynxUsers $users, private readonly ResetUserPassword $passwords) {}

    public static function name(): string
    {
        return 'users.create';
    }

    public static function description(): string
    {
        return 'Create an account with a password and roles.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::default()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Their name.')->required(),
            'email' => $schema->string()->description('The email they sign in with.')->required(),
            'password' => $schema->string()->description('Their password, at least as strong as the app\'s password rules ask.')->required(),
            'roles' => $schema->array()->items($schema->string())->description('Role names to give them.'),
        ];
    }

    /** @return string the new user's id */
    public function handle(Input $input, ActionContext $context): string
    {
        $name = trim((string) $input->string('name'));
        $email = mb_strtolower(trim((string) $input->string('email')));
        $roles = Value::strings($input->array('roles'));

        if ($this->users->emailExists($email)) {
            throw ValidationException::withMessages(['email' => "[{$email}] already has an account."]);
        }

        $user = $this->users->create($name, $email);
        $password = (string) $input->string('password');

        if ($user instanceof Authenticatable) {
            $this->passwords->reset($user, ['password' => $password, 'password_confirmation' => $password]);
        }

        $id = Value::string($user instanceof Authenticatable ? $user->getAuthIdentifier() : null);

        if ($roles !== []) {
            $context->run(AssignRoles::class, ['user' => $id, 'roles' => $roles]);
        }

        $context->changes->created('user', $id, "Create the account {$name} <{$email}>", ['email' => $email, 'roles' => $roles]);

        return $id;
    }
}
