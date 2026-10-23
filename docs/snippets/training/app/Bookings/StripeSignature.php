<?php

declare(strict_types=1);

namespace App\Bookings;

// #region signature
/**
 * Checks the Stripe-Signature header of a webhook: an HMAC of "timestamp.payload" with the
 * endpoint's secret, no older than five minutes. Without this check anyone could post a fake
 * "paid" event to the webhook.
 */
final class StripeSignature
{
    private const int TOLERANCE_SECONDS = 300;

    public static function isValid(string $payload, string $header, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $secret !== ''
            && abs(time() - $timestamp) <= self::TOLERANCE_SECONDS
            && array_any($parts['v1'] ?? [], fn (string $signature): bool => hash_equals($expected, $signature));
    }
}
// #endregion signature
