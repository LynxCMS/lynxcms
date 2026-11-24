<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\SaveTurnstile;
use LynxCms\Forms\Spam\Turnstile;

/**
 * Spam protection on the Forms tab, for superusers (ADR-0148): the Cloudflare Turnstile keys,
 * saved through forms.save_turnstile. The secret is never shown again; leaving it empty keeps the
 * saved one. When .env sets the keys, this only says so.
 *
 * @internal
 */
final class TurnstileSettings extends Component
{
    use Notifies;

    public string $siteKey = '';

    public string $secret = '';

    public ?string $status = null;

    public function boot(AdminAccess $access): void
    {
        $access->authorizeSuperuser();
    }

    public function mount(Turnstile $turnstile): void
    {
        $this->siteKey = $turnstile->isFromEnv() ? '' : $turnstile->siteKey();
    }

    public function save(Turnstile $turnstile): void
    {
        abort_if($turnstile->isFromEnv(), 403);
        $isOn = Value::bool(lynx()->run(SaveTurnstile::class, ['site_key' => $this->siteKey, 'secret' => $this->secret])->value);
        $this->secret = '';
        $this->notify(Value::string(__($isOn ? 'lynx-forms::admin.turnstile.saved' : 'lynx-forms::admin.turnstile.off')));
    }

    public function turnOff(): void
    {
        lynx()->run(SaveTurnstile::class, ['site_key' => '', 'secret' => '']);
        $this->reset('siteKey', 'secret');
        $this->notify(Value::string(__('lynx-forms::admin.turnstile.off')));
    }

    public function render(Turnstile $turnstile): View
    {
        return view('lynx-forms::admin.livewire.turnstile-settings', ['isOn' => $turnstile->isEnabled(), 'isFromEnv' => $turnstile->isFromEnv()]);
    }
}
