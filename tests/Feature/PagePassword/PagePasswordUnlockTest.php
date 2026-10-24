<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\PagePassword\Access\Unlocks;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

/*
 * Step 3 of documentation/plans/page-passwords.md: a locked page answers with the password form;
 * the right password unlocks it for remember_days and redirects back to it.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    lynx()->templates->update('course', ['allow_children' => true]);
    ['open' => $this->open, 'members' => $this->members] = createCourses('open', 'members');
    $this->members->{PagePasswordSchema::FIELD} = 'open sesame';
    $this->members->setLanguageName('pt', 'membros');
    $this->members->save();

    $child = lynx()->pages->new('course', '/courses/members/');
    $child->name = 'inside';
    $child->title = 'Inside members';
    $child->save();
    $this->child = $child;
    RateLimiter::clear('lynx-page-password:127.0.0.1:'.$this->members->id);
});

function unlock(mixed $test, int $pageId, string $password): TestResponse
{
    return $test->post('/lynx-page-password/unlock', ['page' => $pageId, 'password' => $password, 'language' => 'default']);
}

it('answers a locked page with the password form', function (): void {
    $this->get('/courses/members/')
        ->assertForbidden()
        ->assertSee('name="password"', false)
        ->assertDontSee('Members</h1>', false)
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->get('/courses/members/inside/')->assertForbidden();
});

it('leaves public pages alone', function (): void {
    $response = $this->get('/courses/open/')->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBeNull();
});

it('unlocks the page and those below it with the right password', function (): void {
    $response = unlock($this, (int) $this->members->id, 'open sesame');

    $response->assertRedirect('/courses/members/')->assertCookie(Unlocks::COOKIE);
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === Unlocks::COOKIE);
    expect($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());

    $unlocked = $response->getCookie(Unlocks::COOKIE)?->getValue();
    $this->withCookie(Unlocks::COOKIE, (string) $unlocked)->get('/courses/members/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertHeader('Cache-Control', 'no-store, private');
    $this->withCookie(Unlocks::COOKIE, (string) $unlocked)->get('/courses/members/inside/')->assertOk();
});

it('unlocks from a page below with the password above it', function (): void {
    $response = unlock($this, (int) $this->child->id, 'open sesame')->assertRedirect('/courses/members/inside/');

    $this->withCookie(Unlocks::COOKIE, (string) $response->getCookie(Unlocks::COOKIE)?->getValue())->get('/courses/members/')->assertOk();
});

it('shows an error for a wrong password', function (): void {
    unlock($this, (int) $this->members->id, 'nope')->assertRedirect('/courses/members/')->assertCookieMissing(Unlocks::COOKIE);

    $this->get('/courses/members/')->assertForbidden()->assertSee('wrong');
});

it('refuses a sixth try within a minute', function (): void {
    foreach (range(1, 5) as $try) {
        unlock($this, (int) $this->members->id, 'nope');
    }

    unlock($this, (int) $this->members->id, 'open sesame')->assertCookieMissing(Unlocks::COOKIE);
    $this->get('/courses/members/')->assertForbidden()->assertSee('Too many');
});

it('only redirects to the page itself', function (): void {
    $this->post('/lynx-page-password/unlock', ['page' => $this->members->id, 'password' => 'open sesame', 'language' => 'default', 'redirect' => 'https://evil.example'])
        ->assertRedirect('/courses/members/');
});

it('refuses pages that do not exist or have no password', function (): void {
    unlock($this, 999999, 'x')->assertNotFound();
    unlock($this, (int) $this->open->id, 'x')->assertNotFound();
});

it('lets signed-in users with access skip the form', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/courses/members/')->assertOk();
});

it('shows the form in the visitor language', function (): void {
    $this->get('/pt/cursos/membros/')->assertForbidden()->assertSee('protegida');
});

it('shows the form in Canadian French', function (): void {
    lynx()->languages->add('fr', 'Français', locale: 'fr-CA', fallback: 'default');
    $this->members->setLanguageName('fr', 'membres');
    $this->members->save();

    $this->get('/fr/courses/membres/')->assertForbidden()->assertSee('Cette page est protégée')->assertSee('Mot de passe');
});
