<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Auth\Invitation;
use LynxCms\Admin\Auth\InviteUser;
use LynxCms\Admin\Auth\ResendInvitation;
use LynxCms\Core\Support\Value;

/**
 * The Users screen's Add user form (ADR-0041, ADR-0126): an invitation by email through
 * users.invite, or a temporary password through users.create_with_temporary_password, which is the only way when the
 * admin sends no email. Its errors show by the form's inputs.
 *
 * @internal
 */
trait InvitesUsers
{
    public const string BY_EMAIL = 'email';

    public const string BY_PASSWORD = 'password';

    /** @var array{name: string, email: string, roles: list<string>, method: string} */
    public array $invite = ['name' => '', 'email' => '', 'roles' => [], 'method' => self::BY_EMAIL];

    public function mountInvitesUsers(): void
    {
        $this->invite['method'] = $this->defaultMethod();
    }

    public function addUser(): void
    {
        $this->status = null;
        $byPassword = ! AdminAuth::sendsMail() || $this->invite['method'] === self::BY_PASSWORD;
        $byPassword ? $this->createWithTemporaryPassword() : $this->sendInvitation();
        $this->resetInviteForm();
    }

    public function sendInvitation(): void
    {
        abort_unless(AdminAuth::managesAccounts() && AdminAuth::sendsMail(), 404);
        $this->status = null;
        $this->issued = null;
        $this->inInviteForm(fn (): mixed => lynx()->run(InviteUser::class, $this->inviteInput()));
        $this->notify(__('lynx-admin::users.invite.sent', ['email' => Value::string($this->invite['email'])]));
        $this->resetInviteForm();
    }

    public function resendInvitation(string $user): void
    {
        abort_unless(AdminAuth::managesAccounts() && AdminAuth::sendsMail(), 404);
        $this->status = null;
        $email = lynx()->run(ResendInvitation::class, ['user' => $user])->value;
        $this->notify(__('lynx-admin::users.invite.sent_again', ['email' => Value::string($email)]));
    }

    /** @return array{name: string, email: string, roles: list<string>} */
    private function inviteInput(): array
    {
        return ['name' => $this->invite['name'], 'email' => $this->invite['email'], 'roles' => $this->invite['roles']];
    }

    /** Runs an action of the form, showing its errors by the form's inputs. */
    private function inInviteForm(Closure $run): mixed
    {
        abort_unless(AdminAuth::managesAccounts(), 404);

        try {
            return $run();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key): string => 'invite.'.$key, array_keys($e->errors())),
                array_values($e->errors()),
            ));
        }
    }

    private function resetInviteForm(): void
    {
        $this->invite = ['name' => '', 'email' => '', 'roles' => [], 'method' => $this->defaultMethod()];
    }

    private function defaultMethod(): string
    {
        return AdminAuth::sendsMail() ? self::BY_EMAIL : self::BY_PASSWORD;
    }

    /**
     * When each pending invitation on screen was last sent.
     *
     * @param  list<string>  $ids
     * @return array<string, string> user id => date
     */
    private function pendingInvitations(array $ids): array
    {
        return Invitation::query()->whereIn('user_id', $ids)->get()
            ->mapWithKeys(fn (Invitation $invitation): array => [$invitation->user_id => $invitation->sent_at->toFormattedDateString()])->all();
    }
}
