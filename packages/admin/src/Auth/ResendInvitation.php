<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Emails a pending invitation again with a new link (ADR-0041); the old link stops working.
 * Superusers only; never an agent tool.
 */
final class ResendInvitation extends Action
{
    public static function name(): string
    {
        return 'users.resend_invitation';
    }

    public static function description(): string
    {
        return 'Send a pending invitation again, with a new link.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['user' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['user' => $schema->string()->description('Id of the invited user.')->required()];
    }

    public function handle(Input $input, ActionContext $context): string
    {
        $id = Value::string($input->get('user'));
        $invitation = Invitation::query()->where('user_id', $id)->first();
        $user = $invitation === null ? null : Auth::getProvider()->retrieveById($id);

        if ($invitation === null || ! $user instanceof CanResetPassword) {
            throw ValidationException::withMessages(['user' => __('lynx-admin::users.invite.not_pending')]);
        }

        $invitation->forceFill(['sent_at' => now()])->save();
        $context->changes->updated('user', $id, "Resend invitation to {$invitation->email}", ['email' => $invitation->email]);
        $context->event(new UserInvited($id, Value::string(data_get($user, 'name')), $invitation->email, Invitations::broker()->createToken($user)));

        return $invitation->email;
    }
}
