<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\PageStatus;

/*
 * An unpublished page answers 404 to visitors. Signed in users who may view it in the admin
 * (pages.view, superusers) see it on the site with a bar saying it is not published, never
 * cached or indexed, so "View page" works before the page goes live (ADR-0113). The trash stays
 * out of reach.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha');
    $this->alpha->setStatus(PageStatus::UNPUBLISHED)->save();
});

it('answers 404 to visitors', function (): void {
    $this->get('/courses/alpha')->assertNotFound();
});

it('shows an unpublished page to a signed in user who may view it, marked and never cached', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get('/courses/alpha')
        ->assertOk()
        ->assertSee('data-lynx-unpublished', false)
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('shows a page below an unpublished parent too', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED, false)->save();
    $courses = lynx()->pages->get('/courses/');
    $courses->setStatus(PageStatus::UNPUBLISHED)->save();
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get('/courses/alpha')->assertOk()->assertSee('data-lynx-unpublished', false);
});

it('answers 404 to a signed in user who may not view the page', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'guest-ish', 'grants' => [['permission' => 'admin.access']]]);
    lynx()->run(AssignRoles::class, ['user' => '9', 'roles' => ['guest-ish']]);
    $this->actingAs(new GenericUser(['id' => 9]));

    $this->get('/courses/alpha')->assertNotFound();
});

it('keeps trashed pages out of reach for signed in users', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED, false)->save();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->pages->trash($this->alpha);

    $this->get('/courses/alpha')->assertNotFound();
});

it('shows published pages without the bar', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED, false)->save();
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get('/courses/alpha')->assertOk()->assertDontSee('data-lynx-unpublished', false);
});
