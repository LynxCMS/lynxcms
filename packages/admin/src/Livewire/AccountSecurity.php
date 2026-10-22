<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Auth\TwoFactorState;
use LynxCms\Admin\Livewire\Concerns\ChangesPassword;
use LynxCms\Admin\Livewire\Concerns\ManagesPasskeys;
use LynxCms\Admin\Livewire\Concerns\ManagesTwoFactor;
use LynxCms\Admin\Livewire\Concerns\Notifies;

/**
 * Account > Security (ADR-0041): the signed in user's password, two-factor authentication and
 * passkeys. The route asks for the password first. Two-factor authentication and passkeys show
 * when the app's user model uses Fortify's traits.
 *
 * @api
 */
final class AccountSecurity extends Component
{
    use ChangesPassword;
    use ManagesPasskeys;
    use ManagesTwoFactor;
    use Notifies;

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function render(): View
    {
        $twoFactorUser = TwoFactorState::user();
        $passkeyUser = $this->passkeyUser();
        $isPending = $twoFactorUser !== null && $this->isSettingUpTwoFactor && $twoFactorUser->getAttribute('two_factor_secret') !== null;

        return view('lynx-admin::livewire.account-security', [
            'canUseTwoFactor' => $twoFactorUser !== null,
            'twoFactorEnabled' => $twoFactorUser !== null && TwoFactorState::isEnabled($twoFactorUser),
            'qrCodeSvg' => $isPending ? TwoFactorState::qrCodeSvg($twoFactorUser) : null,
            'setupKey' => $isPending ? TwoFactorState::setupKey($twoFactorUser) : null,
            'canUsePasskeys' => $passkeyUser !== null,
            'passkeys' => $passkeyUser === null ? [] : $this->passkeys($passkeyUser),
        ]);
    }
}
