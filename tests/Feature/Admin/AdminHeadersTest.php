<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Testing\TestResponse;

/*
 * Admin and sign-in screens refuse to be framed by other sites, so a page elsewhere cannot lay its
 * own buttons over the admin's (clickjacking), and send no full address to other sites.
 */

beforeEach(function (): void {
    courseSite();
});

function assertAdminHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('Referrer-Policy', 'same-origin');
}

it('sends them on admin screens', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));

    assertAdminHeaders($this->get('/lynx/pages')->assertOk());
});

it('sends them on the sign-in screen', function (): void {
    assertAdminHeaders($this->get('/lynx/login')->assertOk());
});

it('leaves the site\'s own pages alone', function (): void {
    $this->get('/courses')->assertHeaderMissing('X-Frame-Options');
});
