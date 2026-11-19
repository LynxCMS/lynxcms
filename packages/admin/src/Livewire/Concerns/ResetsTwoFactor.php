<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LynxCms\Admin\AdminServiceProvider;
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

    /**
     * The users on screen who can open the admin but have not set up two-factor authentication,
     * while the admin requires it (ADR-0135): they are asked to on their next visit.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $withTwoFactor
     * @return list<string>
     */
    private function usersWithoutTwoFactor(array $ids, array $withTwoFactor): array
    {
        $model = config('auth.providers.users.model');
        $without = array_values(array_diff($ids, $withTwoFactor));

        if ($without === [] || ! TwoFactorRequirement::isRequired() || ! TwoFactorRequirement::canBeSetUp() || ! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return [];
        }

        return Value::strings($model::query()->whereKey($without)->get()
            ->filter(fn (Model $user): bool => $user instanceof Authenticatable && Gate::forUser($user)->allows(AdminServiceProvider::GATE))
            ->map(fn (Model $user): string => Value::string($user->getKey()))->values()->all());
    }
}
