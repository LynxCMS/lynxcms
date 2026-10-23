<?php

declare(strict_types=1);

namespace LynxCms\Forms\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormsTables;
use Override;

/**
 * A form submission (ADR-0052): the answers, a snapshot of the form's fields when it was sent,
 * the page and language it came from, and how editors handled it. A module system table.
 *
 * @property int $id
 * @property string $uuid
 * @property string $form
 * @property int|null $page_id
 * @property int|null $language_id
 * @property string|null $user_id
 * @property array<string, mixed> $data
 * @property list<array<string, mixed>> $fields
 * @property string $status
 * @property string|null $notes
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $read_at
 * @property string|null $read_by
 * @property Carbon $created_at
 * @property Collection<int, SubmissionFile> $files
 *
 * @api
 */
final class Submission extends Model
{
    public const array STATUSES = ['new', 'read', 'archived', 'spam'];

    #[Override]
    protected $table = FormsTables::SUBMISSIONS;

    #[Override]
    protected $guarded = [];

    /** @return HasMany<SubmissionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function language(): ?Language
    {
        return lynx()->languages->find($this->language_id);
    }

    /** The account that sent it, from the app's user provider, or null for a guest. */
    public function user(): ?Authenticatable
    {
        return $this->user_id === null ? null : Auth::guard()->getProvider()->retrieveById($this->user_id);
    }

    /** The sender's account as staff read it: "Name <email>", or its id when the user is gone. */
    public function account(): ?string
    {
        if ($this->user_id === null) {
            return null;
        }

        $user = $this->user();
        $name = Value::string($user?->getAttribute('name') ?? '');
        $email = Value::string($user?->getAttribute('email') ?? '');

        return trim($name.($email === '' ? '' : " <{$email}>")) ?: "User {$this->user_id}";
    }

    /**
     * Submissions a user sent.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function fromUser(Builder $query, int|string $user): void
    {
        $query->where('user_id', (string) $user);
    }

    /** The form's folder of files on the forms disk. */
    public function folder(): string
    {
        return 'forms/'.$this->uuid;
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'fields' => 'array', 'meta' => 'array', 'read_at' => 'datetime'];
    }
}
