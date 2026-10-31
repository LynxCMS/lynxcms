<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use LynxCms\Admin\Auth\TwoFactorState;

/**
 * Account security: two-factor authentication through Fortify's actions. Enabling shows a QR
 * code and needs a code from the authenticator app before it counts; recovery codes show once
 * it is confirmed or when regenerated.
 *
 * @internal
 */
trait ManagesTwoFactor
{
    /** Waiting for a code that confirms the new secret. */
    #[Locked]
    public bool $isSettingUpTwoFactor = false;

    public string $code = '';

    /** @var list<string> shown right after confirming or regenerating */
    #[Locked]
    public array $recoveryCodes = [];

    /** Livewire hook: a setup left without its confirming code does not linger. */
    public function mountManagesTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $user = TwoFactorState::user();

        if ($user !== null && $user->getAttribute('two_factor_secret') !== null && ! TwoFactorState::isEnabled($user)) {
            $disable($user);
        }
    }

    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->twoFactorUser());
        $this->isSettingUpTwoFactor = true;
        $this->recoveryCodes = [];
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $user = $this->twoFactorUser();
        $this->resetErrorBag('code');

        try {
            $confirm($user, $this->code);
        } catch (ValidationException) {
            $this->addError('code', __('lynx-admin::account.two_factor.invalid_code'));

            return;
        }

        $this->isSettingUpTwoFactor = false;
        $this->code = '';
        $this->recoveryCodes = TwoFactorState::recoveryCodes($user->refresh());
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $user = $this->twoFactorUser();
        $generate($user);
        $this->recoveryCodes = TwoFactorState::recoveryCodes($user->refresh());
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->twoFactorUser());
        $this->reset('isSettingUpTwoFactor', 'code', 'recoveryCodes');
    }

    private function twoFactorUser(): Model
    {
        return TwoFactorState::user() ?? abort(404);
    }
}
