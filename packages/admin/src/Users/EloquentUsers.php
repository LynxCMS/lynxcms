<?php

declare(strict_types=1);

namespace LynxCms\Admin\Users;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use LynxCms\Core\Support\Value;

/**
 * The default LynxUsers: the Eloquent model of the app's "users" auth provider, searched by name and
 * email when those columns exist.
 *
 * @internal
 */
final class EloquentUsers implements LynxUsers
{
    private const int UNUSABLE_PASSWORD_LENGTH = 64;

    public function search(string $query, int $limit): array
    {
        $class = $this->model();

        if ($class === null) {
            return [];
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower(trim($query))).'%';
        $users = $class::query()
            ->when(trim($query) !== '', fn ($builder) => $builder->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term]))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return array_values($users->map(fn (Model $user): array => [
            'id' => Value::string($user->getKey()),
            'label' => Value::string($user->getAttribute('name'), Value::string($user->getKey())),
            'detail' => Value::string($user->getAttribute('email')),
        ])->all());
    }

    public function emailExists(string $email): bool
    {
        $class = $this->model();

        return $class !== null && $class::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->exists();
    }

    public function create(string $name, string $email): CanResetPassword
    {
        $class = $this->model() ?? throw new LogicException('The users auth provider has no Eloquent model to create users with.');
        $user = $class::query()->forceCreate([
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => Hash::make(Str::random(self::UNUSABLE_PASSWORD_LENGTH)),
        ]);

        return $user instanceof CanResetPassword ? $user : throw new LogicException("{$class} cannot reset passwords, so invited users could not choose one.");
    }

    /** @return class-string<Model>|null */
    private function model(): ?string
    {
        $class = Value::string(config('auth.providers.users.model'));

        return is_subclass_of($class, Model::class) ? $class : null;
    }
}
