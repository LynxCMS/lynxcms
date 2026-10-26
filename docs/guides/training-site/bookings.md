---
title: Paid Bookings
description: Chapter 7 of the training site guide. Book a session and pay with Stripe Checkout, confirm it with a signed webhook, count seats, and email the buyer in their language.
---

# Paid Bookings

This chapter turns the site into a till. A visitor picks a session, pays with Stripe Checkout, and gets a confirmation email in their language. The session's seat count goes down by itself, and the school sees the booking in Stripe and in its own database.

Lynx keeps the content: the course, the session, its price and its seats are pages and fields that staff edit. The bookings are your app's data, in a table of their own. With that split, you can sell through Lynx without a commerce module.

Out of scope here, and worth planning for a real school: refunds, invoices and VAT. Stripe's dashboard handles refunds, and Stripe Tax and invoicing cover the rest.

## The bookings table

A booking points at its session page by id, and keeps who booked, in which language, and what Stripe said:

<<< @/snippets/training/database/migrations/2026_10_24_000000_create_bookings_table.php{php}

The `Booking` model reads its session back with `lynx()->pages->get($this->session_id)`.

## Booking and paying

The session page shows the seats left and a booking form, which posts to a route of your own. Lynx's routes serve the pages; `routes/web.php` keeps the app's:

<<< @/snippets/training/routes/web.php#bookings{php}

The controller checks that the session is still open, records a pending booking, and sends the buyer to Stripe:

<<< @/snippets/training/app/Http/Controllers/BookingController.php#controller{php}

The price comes from the session, or from its course when the session has none, so staff change prices in the page editor and never in code. The form sends the visitor's language, because `/book/42` is the app's URL and has no `/pt/` in it.

Stripe Checkout takes the payment on Stripe's own page, so card details never reach your server. Creating the session is one HTTP call:

<<< @/snippets/training/app/Bookings/StripeCheckout.php#checkout{php}

## The webhook

Stripe tells your app a payment succeeded by posting to a webhook. Anyone could post to that URL, so the webhook first checks Stripe's signature:

<<< @/snippets/training/app/Bookings/StripeSignature.php#signature{php}

Then it confirms the booking. Stripe may send the same event more than once, so the booking is confirmed, a seat taken and the email sent only the first time:

<<< @/snippets/training/app/Http/Controllers/StripeWebhookController.php#webhook{php}

In Stripe's dashboard, add the endpoint `https://your-site/stripe/webhook` for the `checkout.session.completed` event, and put its signing secret in `.env` as `STRIPE_WEBHOOK_SECRET`, next to `STRIPE_SECRET`. On your machine, `stripe listen --forward-to localhost:8000/stripe/webhook` forwards test events.

## Seats

The seats left are the session page's `seats` field, the same one staff edit. Taking a seat saves the page as the system, since the buyer is not an editor, and the page's history shows the change:

<<< @/snippets/training/app/Bookings/Seats.php#seats{php}

The lock keeps two payments that arrive at the same moment from both taking the last seat. Once seats reach zero, the booking form says the session is full, and the controller refuses it even if an old page is still open in someone's browser.

Session pages will be served from the page cache in chapter 10, so the seats and the form sit in an **island**: the stored page holds a placeholder, and every visitor gets the current count.

```blade
<x-lynx-island view="partials.session.booking">
    <p>…</p>
</x-lynx-island>
```

## The confirmation email

The email is a queued mailable. It reads the course, date and studio in the buyer's language when it is built, so the queue worker that sends it later can't get the language wrong:

<<< @/snippets/training/app/Mail/BookingConfirmed.php#mail{php}

Its texts go through `__()`, with the Portuguese in `lang/pt.json`. Lynx reads `lang/pt.json` for the `pt-PT` locale too.

## What you get

- **The client:** paid bookings with no manual step: the money in Stripe, the seat counted, the buyer emailed in their language.
- **You:** content and transactions kept apart, prices that staff own, a webhook that is safe against forgery and repeats, and tests that run the whole flow with a faked Stripe.
