<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Providers\FortifyServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

/*
 * Chapter 8 of the training site guide: sign-up and sign-in with the site's own Fortify, the
 * members area, and course materials only for the people who booked.
 */

require_once __DIR__.'/../Feature/Docs/Training/training.php';
require_once TRAINING.'/app/Models/User.php';
require_once TRAINING.'/app/Actions/Fortify/CreateNewUser.php';
require_once TRAINING.'/app/Actions/Fortify/ResetUserPassword.php';
require_once TRAINING.'/app/Providers/FortifyServiceProvider.php';

beforeEach(function (): void {
    trainingSite();
    new FortifyServiceProvider(app())->boot();
    $this->withoutVite();
    $this->course = lynx()->pages->get('/courses/yoga-teacher-training/');
    $this->materials = '/courses/yoga-teacher-training/materials';
});

/** A member as sign-up makes one: with the guest role, so roles are in use as on a live site. */
function trainingMember(string $email = 'mia@example.com'): User
{
    $user = User::query()->forceCreate(['name' => 'Mia Member', 'email' => $email, 'password' => Hash::make('long enough pass')]);
    event(new Registered($user));

    return $user;
}

function trainingPaidBooking(User $user, int $courseId): Booking
{
    $session = lynx()->pages->get($courseId)->children('template=session')->first();

    return Booking::query()->create([
        'uuid' => (string) Str::uuid(), 'session_id' => $session->id, 'user_id' => $user->id, 'name' => $user->name,
        'email' => $user->email, 'language' => 'en', 'amount' => 240000, 'currency' => 'eur', 'status' => 'paid', 'paid_at' => now(),
    ]);
}

it('signs a visitor up as a guest, into the members area and never the admin', function (): void {
    $this->get('/register')->assertOk()->assertSee('Create an account');

    $this->post('/register', ['name' => 'Mia Member', 'email' => 'mia@example.com', 'password' => 'long enough pass', 'password_confirmation' => 'long enough pass'])
        ->assertRedirect('/account');

    $mia = User::query()->where('email', 'mia@example.com')->sole();
    expect(RoleAssignment::query()->where('user_id', (string) $mia->id)->value('role_id'))->toBe(Role::query()->where('name', 'guest')->value('id'));

    $this->get('/account')->assertOk()->assertSee('No bookings yet');
    $this->get('/lynx/pages')->assertForbidden();
});

it('sends guests who open course materials to sign in', function (): void {
    $this->get($this->materials)->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('Forgot your password?');
});

it('tells members who have not booked the course what the materials are for', function (): void {
    $this->actingAs(trainingMember());

    $this->get($this->materials)->assertForbidden()->assertSee('These materials open once you have booked a session of the course.');
});

it('opens the materials to members who booked, and links them from the members area', function (): void {
    $mia = trainingMember();
    trainingPaidBooking($mia, $this->course->id);
    $this->actingAs($mia);

    $this->get($this->materials)->assertOk()->assertSee('Your reading list and handouts.');
    $this->get('/account')->assertOk()->assertSee('Yoga teacher training, ')->assertSee($this->materials, false);
    $this->get('/courses/sports-nutrition/materials')->assertForbidden();
});

it('keeps a booking made while signed in in the member\'s area', function (): void {
    config(['atlas.stripe' => ['secret' => 'sk_test', 'webhook_secret' => 'whsec']]);
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);
    $mia = trainingMember();
    $session = $this->course->children('template=session')->first();

    $this->actingAs($mia)->post(route('bookings.store', $session->id), ['name' => 'Mia', 'email' => 'mia@example.com', 'language' => 'en']);

    expect(Booking::query()->sole()->user_id)->toBe($mia->id);
    $this->get('/account')->assertSee('pending');
});

it('lets only the people who booked download the course\'s private files', function (): void {
    $download = lynx()->pages->get($this->materials.'/')->downloads->first();

    expect($download->url())->toContain('/lynx-media/');
    $this->get($download->url())->assertRedirect('/login');

    $this->actingAs(trainingMember('nobody@example.com'));
    $this->get($download->url())->assertForbidden();

    $mia = trainingMember();
    trainingPaidBooking($mia, $this->course->id);
    $this->actingAs($mia);
    expect($this->get($download->url())->assertOk()->streamedContent())->toBe('Reading list for yoga-teacher-training');
});
