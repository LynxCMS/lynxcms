<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Auth\CreateUserWithTemporaryPassword;
use LynxCms\Admin\Auth\SetTemporaryPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Core\Support\Value;

/**
 * Temporary passwords on the Users screen (ADR-0126): a new user gets one instead of an
 * invitation, or a listed user gets a new one. The password shows once, in $issued, until
 * dismissed; nothing else on the screen ever holds it.
 *
 * @internal
 */
trait GivesTemporaryPasswords
{
    /** @var array{name: string, password: string, until: string, isNew: bool}|null */
    #[Locked]
    public ?array $issued = null;

    public function giveTemporaryPassword(string $user): void
    {
        abort_unless(AdminAuth::managesAccounts(), 404);
        $this->status = null;
        $given = Value::array(lynx()->run(SetTemporaryPassword::class, ['user' => $user])->value);
        $this->issue($this->userName($user), $given, isNew: false);
    }

    public function dismissIssued(): void
    {
        $this->issued = null;
    }

    private function createWithTemporaryPassword(): void
    {
        $created = Value::array($this->inInviteForm(fn (): mixed => lynx()->run(CreateUserWithTemporaryPassword::class, $this->inviteInput())->value));
        $this->issue(Value::string($this->invite['name']), $created, isNew: true);
    }

    /** @param array<array-key, mixed> $given */
    private function issue(string $name, array $given, bool $isNew): void
    {
        $this->issued = [
            'name' => $name,
            'password' => Value::string($given['password'] ?? ''),
            'until' => Carbon::parse(Value::string($given['expires_at'] ?? ''))->isoFormat('lll'),
            'isNew' => $isNew,
        ];
    }

    private function userName(string $id): string
    {
        $user = auth()->getProvider()->retrieveById($id);

        return Value::string(data_get($user, 'name'), $id);
    }

    /**
     * Until when each user on screen with a temporary password may use it.
     *
     * @param  list<string>  $ids
     * @return array<string, string> user id => date and time
     */
    private function temporaryPasswords(array $ids): array
    {
        return TemporaryPassword::query()->whereIn('user_id', $ids)->get()
            ->mapWithKeys(fn (TemporaryPassword $temporary): array => [$temporary->user_id => $temporary->expires_at->isoFormat('lll')])->all();
    }
}
