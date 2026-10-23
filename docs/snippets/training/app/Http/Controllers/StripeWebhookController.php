<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Bookings\Seats;
use App\Bookings\StripeSignature;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// #region webhook
/**
 * Stripe calls this when a Checkout Session completes. Stripe may call more than once, so a
 * booking is confirmed, a seat taken and the email sent only the first time.
 */
final class StripeWebhookController
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        if (! StripeSignature::isValid($payload, (string) $request->header('Stripe-Signature'), (string) config('atlas.stripe.webhook_secret'))) {
            Log::warning('Refused a Stripe webhook with a bad signature.', ['ip' => $request->ip()]);

            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);

        if (($event['type'] ?? null) === 'checkout.session.completed' && ($event['data']['object']['payment_status'] ?? null) === 'paid') {
            $this->confirm((string) $event['data']['object']['id']);
        }

        // Stripe retries anything but a 2xx, so other events are acknowledged and ignored.
        return response('OK');
    }

    private function confirm(string $stripeSession): void
    {
        $booking = DB::transaction(function () use ($stripeSession): ?Booking {
            $booking = Booking::query()->where('stripe_session', $stripeSession)->lockForUpdate()->first();

            if ($booking === null || $booking->isPaid()) {
                return null;
            }

            $booking->update(['status' => 'paid', 'paid_at' => now()]);

            return $booking;
        });

        if ($booking !== null) {
            Seats::take($booking->session_id);
            Mail::to($booking->email)->send(new BookingConfirmed($booking));
        }
    }
}
// #endregion webhook
