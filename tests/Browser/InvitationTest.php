<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use LynxCms\Admin\Auth\InvitationNotification;
use LynxCms\Admin\Auth\InviteUser;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionResult;

/*
 * Accepting an invitation: the link in the email opens a screen to choose a password, which
 * signs the new user in.
 */

beforeEach(function (): void {
    signInSite();
    Notification::fake();
    $admin = browserUser('ada@example.com');
    lynx()->actingAs(Actor::user($admin->id), fn (): ActionResult => lynx()->run(InviteUser::class, ['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'roles' => ['editor']]));

    $this->link = '';
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        // The link's path and query: the browser tests' server runs on its own address.
        $this->link = parse_url($notification->url, PHP_URL_PATH).'?'.parse_url($notification->url, PHP_URL_QUERY);

        return true;
    });
});

it('chooses a password from the invitation link and opens the admin', function (): void {
    visit($this->link)
        ->assertSee('Welcome, Bruno Costa')
        ->fill('password', NEW_INVITED_PASSWORD)
        ->fill('password_confirmation', NEW_INVITED_PASSWORD)
        ->click('button[type=submit]')
        ->assertPathIsNot('/lynx/login')
        ->assertSee('Sign out')
        ->assertNoJavaScriptErrors();
});

it('refuses the same link a second time, without naming anyone', function (): void {
    $path = $this->link;

    visit($path)
        ->fill('password', NEW_INVITED_PASSWORD)
        ->fill('password_confirmation', NEW_INVITED_PASSWORD)
        ->click('button[type=submit]')
        ->press('Sign out')
        ->navigate($path)
        ->assertDontSee('Bruno Costa')
        ->fill('password', NEW_INVITED_PASSWORD)
        ->fill('password_confirmation', NEW_INVITED_PASSWORD)
        ->click('button[type=submit]')
        ->assertSee('This invitation has expired or was already used.')
        ->assertDontSee('Sign out');
});

const NEW_INVITED_PASSWORD = 'Invited-Password-2026';
