<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LynxCms\Core\Pages\Page;
use Override;

/**
 * A place on a session: pending until Stripe confirms the payment, then paid.
 *
 * @property int $id
 * @property string $uuid
 * @property int $session_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $language
 * @property int $amount in cents
 * @property string $currency
 * @property string $status pending, paid or cancelled
 * @property string|null $stripe_session
 * @property Carbon|null $paid_at
 */
final class Booking extends Model
{
    #[Override]
    protected $guarded = [];

    /** The session page this booking is for. */
    public function session(): ?Page
    {
        return lynx()->pages->get($this->session_id);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
