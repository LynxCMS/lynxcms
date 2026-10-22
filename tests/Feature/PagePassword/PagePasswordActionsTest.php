<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Templates\Layout\LayoutBlock;
use LynxCms\Core\Templates\Layout\TemplateLayout;
use LynxCms\PagePassword\Actions\DisablePagePassword;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\Actions\InstallPagePassword;
use LynxCms\PagePassword\PagePasswordSchema;
use LynxCms\PagePassword\StoredPassword;

beforeEach(fn (): Lynx => courseSite());

it('installs the password field once', function (): void {
    expect(lynx()->run(InstallPagePassword::class)->value)->toBeTrue()
        ->and(lynx()->run(InstallPagePassword::class)->value)->toBeFalse()
        ->and(lynx()->fields->find(PagePasswordSchema::FIELD)?->type)->toBe('password');
});

it('adds the password field to a template, in the sidebar', function (): void {
    expect(lynx()->run(EnablePagePassword::class, ['template' => 'course'])->value)->toBeTrue()
        ->and(lynx()->run(EnablePagePassword::class, ['template' => 'course'])->value)->toBeFalse();

    $layout = TemplateLayout::for(lynx()->templates->get('course'));
    expect(array_map(fn (LayoutBlock $block): ?string => $block->field, $layout->blocks('content', TemplateLayout::SIDEBAR)))->toBe([PagePasswordSchema::FIELD]);
});

it('refuses a template that does not exist or has no URL', function (): void {
    lynx()->templates->add('settings-only', fields: ['title'], hasUrl: false);

    expect(fn (): ActionResult => lynx()->run(EnablePagePassword::class, ['template' => 'nope']))->toThrow(ValidationException::class)
        ->and(fn (): ActionResult => lynx()->run(EnablePagePassword::class, ['template' => 'settings-only']))->toThrow(ValidationException::class, 'no URL');
});

it('rolls back when a hook halts it', function (): void {
    lynx()->hook(EnablePagePassword::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(EnablePagePassword::class, ['template' => 'course']))->toThrow(ActionHalted::class)
        ->and(lynx()->fields->find(PagePasswordSchema::FIELD))->toBeNull();
});

it('changes nothing on a dry run but lists the changes', function (): void {
    $result = lynx()->run(EnablePagePassword::class, ['template' => 'course'], dryRun: true);

    expect($result->changes->all())->not->toBeEmpty()
        ->and(lynx()->fields->find(PagePasswordSchema::FIELD))->toBeNull();
});

it('removes the field from a template and keeps the passwords', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->{PagePasswordSchema::FIELD} = 'open sesame';
    $alpha->save();

    expect(lynx()->run(DisablePagePassword::class, ['template' => 'course'])->value)->toBeTrue()
        ->and(lynx()->templates->fields('course')->has(PagePasswordSchema::FIELD))->toBeFalse();

    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    $password = lynx()->pages->get($alpha->id)?->get(PagePasswordSchema::FIELD);

    expect($password)->toBeInstanceOf(StoredPassword::class)
        ->and($password->matches('open sesame'))->toBeTrue();
});

it('stores passwords encrypted, and editors can read them back', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->{PagePasswordSchema::FIELD} = '  open sesame ';
    $alpha->save();
    $stored = DB::table((string) lynx()->fields->find(PagePasswordSchema::FIELD)?->table())->where('page_id', $alpha->id)->value('data');
    $page = lynx()->pages->get($alpha->id);
    $password = $page?->get(PagePasswordSchema::FIELD);

    expect($stored)->not->toContain('sesame')
        ->and($password->isSet())->toBeTrue()
        ->and($password->matches('open sesame'))->toBeTrue()
        ->and($password->matches('open'))->toBeFalse()
        ->and($password->reveal())->toBe('open sesame');

    // Saving the stored value again (imports, revisions) keeps the same password.
    $page->{PagePasswordSchema::FIELD} = $page->getUnformatted(PagePasswordSchema::FIELD);
    $page->save();
    expect(lynx()->pages->get($alpha->id)?->get(PagePasswordSchema::FIELD)->matches('open sesame'))->toBeTrue();

    $page->{PagePasswordSchema::FIELD} = '';
    $page->save();
    expect(lynx()->pages->get($alpha->id)?->get(PagePasswordSchema::FIELD)->isSet())->toBeFalse();
});

it('cannot be used in selectors or found by search', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->{PagePasswordSchema::FIELD} = 'findable';
    $alpha->save();

    expect(lynx()->pages->find('search*=findable')->all())->toBe([])
        ->and(fn (): mixed => lynx()->pages->find(PagePasswordSchema::FIELD.'=findable'))->toThrow(SelectorException::class);
});
