<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Seo\Actions\DisableSeo;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Actions\InstallSeo;
use LynxCms\Seo\SeoSchema;

beforeEach(fn (): Lynx => courseSite());

/** @return list<string> */
function courseFieldNames(): array
{
    return lynx()->templates->fields('course')->keys()->all();
}

it('installs the settings template and its hidden page once', function (): void {
    lynx()->run(InstallSeo::class);
    lynx()->run(InstallSeo::class);

    $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);

    expect($settings)->toBeInstanceOf(Page::class)
        ->and($settings->name())->toBe(SeoSchema::SETTINGS_NAME)
        ->and($settings->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        ->and($settings->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(lynx()->pages->count('template='.SeoSchema::SETTINGS_TEMPLATE.', include=all'))->toBe(1)
        ->and(lynx()->templates->get(SeoSchema::SETTINGS_TEMPLATE)->hasUrl())->toBeFalse()
        ->and(lynx()->run(InstallSeo::class)->value)->toBeFalse();
});

it('adds the SEO fields to a template, installing first', function (): void {
    $result = lynx()->run(EnableSeo::class, ['template' => 'course']);

    expect($result->value)->toBeTrue()
        ->and(courseFieldNames())->toContain(...SeoSchema::pageFields())
        ->and(lynx()->templates->fields('course')->get(SeoSchema::TITLE)?->description())->toContain('search results')
        ->and(lynx()->settings(SeoSchema::SETTINGS_TEMPLATE))->toBeInstanceOf(Page::class)
        ->and(lynx()->run(EnableSeo::class, ['template' => 'course'])->value)->toBeFalse();
});

it('refuses a template that does not exist or has no URL', function (): void {
    lynx()->run(InstallSeo::class);

    expect(fn (): ActionResult => lynx()->run(EnableSeo::class, ['template' => 'nope']))->toThrow(ValidationException::class)
        ->and(fn (): ActionResult => lynx()->run(EnableSeo::class, ['template' => SeoSchema::SETTINGS_TEMPLATE]))->toThrow(ValidationException::class, 'no URL')
        ->and(fn (): ActionResult => lynx()->run(EnableSeo::class, []))->toThrow(ValidationException::class);
});

it('rolls back the whole install when a hook halts it', function (): void {
    lynx()->hook(EnableSeo::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(EnableSeo::class, ['template' => 'course']))->toThrow(ActionHalted::class)
        ->and(courseFieldNames())->not->toContain(SeoSchema::TITLE)
        ->and(lynx()->templates->find(SeoSchema::SETTINGS_TEMPLATE))->toBeNull()
        ->and(lynx()->fields->find(SeoSchema::TITLE))->toBeNull();
});

it('changes nothing on a dry run but lists the changes', function (): void {
    $result = lynx()->run(EnableSeo::class, ['template' => 'course'], dryRun: true);

    expect($result->changes->all())->not->toBeEmpty()
        ->and(courseFieldNames())->not->toContain(SeoSchema::TITLE)
        ->and(lynx()->templates->find(SeoSchema::SETTINGS_TEMPLATE))->toBeNull();
});

it('removes the SEO fields from a template and keeps their values', function (): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => [SeoSchema::TITLE => ['default' => 'Kept']]]);

    $result = lynx()->run(DisableSeo::class, ['template' => 'course']);
    lynx()->run(EnableSeo::class, ['template' => 'course']);

    expect($result->value)->toBeTrue()
        ->and((string) lynx()->pages->get($alpha->id)->get(SeoSchema::TITLE))->toBe('Kept')
        ->and(lynx()->run(DisableSeo::class, ['template' => 'courses'])->value)->toBeFalse();
});

it('refuses to install over a field of the same name with other settings', function (): void {
    lynx()->fields->add(SeoSchema::TITLE, 'integer');

    expect(fn (): ActionResult => lynx()->run(InstallSeo::class))->toThrow(ValidationException::class, SeoSchema::TITLE);
});
