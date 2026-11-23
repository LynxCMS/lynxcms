<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/*
 * Shortcuts on lynx(): page status verbs over setStatus(), and create() beside add() on the
 * schema APIs. Each runs the same action as the longer form.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha');
});

it('unpublishes and publishes a page', function (): void {
    $unpublished = lynx()->pages->unpublish('/courses/alpha/');
    $published = lynx()->pages->publish($this->alpha);

    expect($unpublished)->toBeInstanceOf(Page::class)
        ->and($unpublished->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and($published->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('hides and unhides a page', function (): void {
    expect(lynx()->pages->hide($this->alpha)->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(lynx()->pages->unhide($this->alpha)->hasStatus(PageStatus::HIDDEN))->toBeFalse();
});

it('locks and unlocks a page', function (): void {
    expect(lynx()->pages->lock((int) $this->alpha->id)->hasStatus(PageStatus::LOCKED))->toBeTrue()
        ->and(lynx()->pages->unlock((int) $this->alpha->id)->hasStatus(PageStatus::LOCKED))->toBeFalse();
});

it('changes nothing on a dry run', function (): void {
    $result = lynx()->pages->unpublish($this->alpha, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->pages->get($this->alpha->id)->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('creates fields, templates and languages with create()', function (): void {
    expect(lynx()->fields->create('subtitle', 'text', translatable: true))->toBeInstanceOf(Field::class)
        ->and(lynx()->templates->create('event', fields: ['title', 'subtitle'], label: 'Event'))->toBeInstanceOf(Template::class)
        ->and(lynx()->languages->create('es', 'Español', locale: 'es-ES'))->toBeInstanceOf(Language::class)
        ->and(lynx()->templates->fields('event')->keys()->all())->toBe(['title', 'subtitle']);
});

it('runs create() as a dry run', function (): void {
    expect(lynx()->fields->create('subtitle', 'text', dryRun: true))->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->fields->find('subtitle'))->toBeNull();
});
