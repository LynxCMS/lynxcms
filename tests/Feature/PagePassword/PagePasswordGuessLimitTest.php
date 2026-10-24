<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

/*
 * Guessing a page password is limited per address and, across every address, per page: many
 * addresses together can't try more than lynx-page-password.max_failures_per_hour wrong ones.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    ['members' => $members] = createCourses('members');
    $members->{PagePasswordSchema::FIELD} = 'a long shared passphrase';
    $members->save();
    $this->membersId = (int) $members->id;
    config(['lynx-page-password.max_failures_per_hour' => 3]);
});

function tryPassword(mixed $test, string $ip, string $password): mixed
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/lynx-page-password/unlock', ['page' => $test->membersId, 'password' => $password, 'language' => 'default']);
}

it('locks a page for everyone after too many wrong passwords from any addresses', function (): void {
    Log::spy();

    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
        tryPassword($this, $ip, 'guess')->assertSessionHasErrors(['password' => 'That password is wrong.']);
    }

    tryPassword($this, '10.0.0.4', 'a long shared passphrase')
        ->assertSessionHasErrors(['password' => 'Too many wrong passwords for this page. Try again in an hour.'])
        ->assertCookieMissing('lynx_page_password');
    Log::shouldHaveReceived('warning')->once();
});

it('does not count right passwords against the page', function (): void {
    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
        tryPassword($this, $ip, 'a long shared passphrase')->assertSessionHasNoErrors()->assertCookie('lynx_page_password');
    }
});
