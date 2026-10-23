<?php

declare(strict_types=1);

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use LynxCms\Core\Pages\Page;

/*
 * Chapter 7 of the training site guide: booking a session and paying with Stripe Checkout, the
 * webhook that confirms it, seats and the confirmation email.
 */

require_once __DIR__.'/training.php';

beforeEach(function (): void {
    trainingSite();
    config(['atlas.stripe' => ['secret' => 'sk_test_atlas', 'webhook_secret' => 'whsec_atlas']]);
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
    Mail::fake();
    $this->withoutVite();
    $this->session = lynx()->pages->findOne('template=session, sort=starts');
});

function trainingSession(): Page
{
    return lynx()->pages->get(test()->session->id);
}

/** Posts a Stripe event to the webhook, signed as Stripe signs it. */
function trainingStripeEvent(array $event, string $secret = 'whsec_atlas'): TestResponse
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return test()->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload);
}

function trainingPaidEvent(string $session = 'cs_test_1'): array
{
    return ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $session, 'payment_status' => 'paid']]];
}

it('shows the booking form with the seats left on a session page', function (): void {
    $this->get($this->session->url)->assertOk()->assertSee('16')->assertSee('seats left')->assertSee('Book and pay');
});

it('sends the buyer to Stripe Checkout with the session\'s price', function (): void {
    $this->post(route('bookings.store', $this->session->id), ['name' => 'Rita Sousa', 'email' => 'rita@example.com', 'language' => 'pt'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $booking = Booking::query()->sole();
    expect($booking->status)->toBe('pending')->and($booking->amount)->toBe(240000)->and($booking->stripe_session)->toBe('cs_test_1');
    Http::assertSent(fn (ClientRequest $request): bool => $request['line_items'][0]['price_data']['unit_amount'] === 240000
        && $request['client_reference_id'] === $booking->uuid && $request['locale'] === 'pt');
});

it('confirms the booking once, takes one seat and mails the buyer in their language', function (): void {
    $this->post(route('bookings.store', $this->session->id), ['name' => 'Rita Sousa', 'email' => 'rita@example.com', 'language' => 'pt']);

    trainingStripeEvent(trainingPaidEvent())->assertOk();
    trainingStripeEvent(trainingPaidEvent())->assertOk(); // Stripe may send an event twice

    expect(Booking::query()->sole()->status)->toBe('paid')
        ->and((int) trainingSession()->seats)->toBe(15);
    Mail::assertQueued(BookingConfirmed::class, 1);
    Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail): bool => $mail->hasTo('rita@example.com')
        && $mail->course === 'Formação de professores de yoga' && $mail->locale === 'pt');
});

it('refuses webhooks without Stripe\'s signature', function (): void {
    $this->post(route('bookings.store', $this->session->id), ['name' => 'Rita', 'email' => 'rita@example.com', 'language' => 'en']);

    trainingStripeEvent(trainingPaidEvent(), 'whsec_forged')->assertStatus(400);

    expect(Booking::query()->sole()->status)->toBe('pending')->and((int) trainingSession()->seats)->toBe(16);
});

it('refuses to book a full or cancelled session', function (): void {
    $session = trainingSession();
    $session->seats = 0;
    $session->save();

    $this->from($session->url)->post(route('bookings.store', $session->id), ['name' => 'Rita', 'email' => 'rita@example.com', 'language' => 'en'])
        ->assertRedirect($session->url)->assertSessionHasErrors('session');

    expect(Booking::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('welcomes the buyer back, in their language, while the payment is confirmed', function (): void {
    $this->post(route('bookings.store', $this->session->id), ['name' => 'Rita', 'email' => 'rita@example.com', 'language' => 'pt']);
    $booking = Booking::query()->sole();

    $this->get(route('bookings.show', $booking->uuid))->assertOk()->assertSee('Estamos a confirmar o pagamento');
    trainingStripeEvent(trainingPaidEvent());
    $this->get(route('bookings.show', $booking->uuid))->assertOk()->assertSee('Obrigado, o seu lugar está confirmado.');
});

it('says payments are not set up instead of failing, on a copy without Stripe keys', function (): void {
    config(['atlas.stripe.secret' => null]);

    $this->from($this->session->url)->post(route('bookings.store', $this->session->id), ['name' => 'Rita', 'email' => 'rita@example.com', 'language' => 'en'])
        ->assertRedirect($this->session->url)->assertSessionHasErrors(['session' => 'Online payment is not set up yet. Please contact us to book.']);

    Http::assertNothingSent();
});
