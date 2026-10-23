<?php

declare(strict_types=1);

namespace App\Bookings;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;

// #region checkout
/**
 * Creates Stripe Checkout Sessions over Stripe's REST API with Laravel's HTTP client: no SDK
 * needed, and tests fake it with Http::fake(). stripe/stripe-php does the same in more words.
 */
final class StripeCheckout
{
    /** Stripe's page where the buyer pays. */
    public function urlFor(Booking $booking, string $description): string
    {
        $session = Http::asForm()
            ->withToken((string) config('atlas.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'customer_email' => $booking->email,
                'client_reference_id' => $booking->uuid,
                'locale' => $booking->language === 'pt' ? 'pt' : 'en',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $booking->currency,
                        'unit_amount' => $booking->amount,
                        'product_data' => ['name' => $description],
                    ],
                ]],
                'success_url' => route('bookings.show', $booking->uuid),
                'cancel_url' => $booking->session()?->url,
            ])
            ->throw()
            ->json();

        $booking->update(['stripe_session' => $session['id']]);

        return $session['url'];
    }
}
// #endregion checkout
