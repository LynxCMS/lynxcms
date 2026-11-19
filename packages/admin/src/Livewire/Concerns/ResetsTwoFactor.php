<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use LynxCms\Admin\Auth\ResetTwoFactor;
use LynxCms\Admin\Auth\TwoFactorRequirement;
use LynxCms\Core\Support\Value;

/**
 * Reset two-factor on the Users screen (ADR-0128), for a user who lost their phone and their
 * recovery codes.
 *
 * @internal
 */
trait ResetsTwoFactor
{
    public function resetTwoFactor(string $user): void
    {
        $this->status = null;
        $name = Value::string(lynx()->run(ResetTwoFactor::class, ['user' => $user])->value);
        $this->notify(__('lynx-admin::users.two_factor.reset_done', ['name' => $name]));
    }

    /**
     * The users on screen who have two-factor authentication on.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function twoFactorUsers(array $ids): array
    {
        $model = config('auth.providers.users.model');

        if (! TwoFactorRequirement::canBeSetUp() || ! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return [];
        }

        return Value::strings($model::query()->whereKey($ids)->whereNotNull('two_factor_confirmed_at')->pluck((new $model)->getKeyName())->map(fn (mixed $id): string => Value::string($id))->all());
    }
}
