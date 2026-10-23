<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Bookings\Seats;
use App\Bookings\StripeCheckout;
use App\Models\Booking;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LynxCms\Core\Pages\Page;

// #region controller
/**
 * Books a place on a session: checks it is still open, records a pending booking, and sends the
 * buyer to Stripe to pay. Stripe's webhook confirms it (StripeWebhookController).
 */
final class BookingController
{
    private const string CURRENCY = 'eur';

    public function store(Request $request, int $session, StripeCheckout $checkout): RedirectResponse
    {
        $page = lynx()->pages->get($session);
        abort_unless($page instanceof Page && $page->template()->name === 'session', 404);

        // This route is the app's, outside Lynx's /pt/ URLs, so the form says the language.
        $buyer = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'language' => ['required', 'string', fn (string $attribute, mixed $code, Closure $fail) => lynx()->languages->find((string) $code) ?? $fail('Unknown language.')],
        ]);

        // A copy without Stripe keys, such as a fresh clone of the starter, can't take payments yet.
        if (blank(config('atlas.stripe.secret'))) {
            return back()->withErrors(['session' => __('Online payment is not set up yet. Please contact us to book.')]);
        }

        if (! Seats::areLeft($page)) {
            return back()->withErrors(['session' => __('This session is full or no longer open.')]);
        }

        $course = $page->parent();
        $booking = Booking::query()->create([
            ...$buyer,
            'uuid' => (string) Str::uuid(),
            'session_id' => $page->id,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'amount' => (int) round(100 * (float) (string) ($page->price ?? $course?->price)),
            'currency' => self::CURRENCY,
        ]);

        return redirect()->away($checkout->urlFor($booking, (string) $page->title));
    }

    /** Where Stripe sends the buyer back: paid, or still waiting for the webhook. */
    public function show(string $uuid): string
    {
        $booking = Booking::query()->where('uuid', $uuid)->firstOrFail();

        return lynx()->language($booking->language, fn (): string => view('bookings.show', [
            'booking' => $booking,
            'page' => $booking->session() ?? abort(404),
        ])->render());
    }
}
// #endregion controller
