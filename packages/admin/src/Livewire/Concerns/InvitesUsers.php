<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Auth\Invitation;
use LynxCms\Admin\Auth\InviteUser;
use LynxCms\Admin\Auth\ResendInvitation;
use LynxCms\Core\Support\Value;

/**
 * The Users screen's Invite user form (ADR-0041), through users.invite. Its errors show by the
 * form's inputs.
 *
 * @internal
 */
trait InvitesUsers
{
    /** @var array{name: string, email: string, roles: list<string>} */
    public array $invite = ['name' => '', 'email' => '', 'roles' => []];

    public function sendInvitation(): void
    {
        abort_unless(AdminAuth::managesAccounts(), 404);
        $this->status = null;

        try {
            lynx()->run(InviteUser::class, $this->invite);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key): string => 'invite.'.$key, array_keys($e->errors())),
                array_values($e->errors()),
            ));
        }

        $this->notify('Invitation sent to '.Value::string($this->invite['email']).'.');
        $this->invite = ['name' => '', 'email' => '', 'roles' => []];
    }

    public function resendInvitation(string $user): void
    {
        abort_unless(AdminAuth::managesAccounts(), 404);
        $this->status = null;
        $email = lynx()->run(ResendInvitation::class, ['user' => $user])->value;
        $this->notify('Invitation sent again to '.Value::string($email).'.');
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
