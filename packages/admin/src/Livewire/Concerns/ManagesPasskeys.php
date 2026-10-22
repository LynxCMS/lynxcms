<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;
use LynxCms\Core\Support\Value;

/**
 * Account security: the user's passkeys. Adding one is a browser ceremony (resources/js/passkeys.js)
 * that posts to Fortify; this lists and removes them.
 *
 * @internal
 */
trait ManagesPasskeys
{
    public function deletePasskey(int $id, DeletePasskey $delete): void
    {
        $user = $this->passkeyUser() ?? abort(404);
        $passkey = Passkey::query()->whereKey($id)->where('user_id', $user->getAuthIdentifier())->first() ?? abort(404);
        $delete($user, $passkey);
    }

    /** Livewire hook for the browser after it registers a passkey: re-render the list. */
    public function passkeyAdded(): void
    {
        $this->notify('Passkey added.');
    }

    private function passkeyUser(): ?Authenticatable
    {
        $user = auth()->user();

        return Features::canManagePasskeys() && $user instanceof PasskeyUser ? $user : null;
    }

    /**
     * @return list<array{id: int, name: string, authenticator: string|null, added: string, used: string|null}>
     */
    private function passkeys(Authenticatable $user): array
    {
        return array_values(Passkey::query()->where('user_id', $user->getAuthIdentifier())->latest()->get()->map(fn (Passkey $passkey): array => [
            'id' => Value::int($passkey->getKey()),
            'name' => (string) $passkey->name,
            'authenticator' => $passkey->authenticator,
            'added' => (string) $passkey->created_at?->diffForHumans(),
            'used' => $passkey->last_used_at?->diffForHumans(),
        ])->all());
    }
}
