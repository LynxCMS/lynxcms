<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Events\LanguageCreated;
use LynxCms\Core\Lynx;
use LynxCms\Core\Models\Language;

beforeEach(fn (): Lynx => installLynx());

it('runs an action and records activity', function (): void {
    lynx()->languages->add('pt', 'Português');

    $event = ActivityEvent::query()->latest('id')->first();

    expect($event->action)->toBe('languages.create')
        ->and($event->subject_type)->toBe('language')
        ->and($event->actor_kind)->toBe('cli')
        ->and($event->changes[0]['summary'])->toContain('Create language pt');
});

it('returns the changes of a dry run and writes nothing', function (): void {
    $activityBefore = ActivityEvent::query()->count();

    $result = lynx()->languages->add('es', 'Español', dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->dryRun)->toBeTrue()
        ->and($result->changes->summary()[0])->toContain('Create language es')
        ->and(Language::query()->where('code', 'es')->exists())->toBeFalse()
        ->and(lynx()->languages->find('es'))->toBeNull()
        ->and(ActivityEvent::query()->count())->toBe($activityBefore);
});

it('lets a before hook stop an action', function (): void {
    lynx()->hook(CreateLanguage::class)->before(function (Input $input): void {
        if ($input->string('code') === 'xx') {
            throw new ActionHalted('Not allowed.');
        }
    });

    expect(fn () => lynx()->languages->add('xx', 'Blocked'))->toThrow(ActionHalted::class)
        ->and(Language::query()->where('code', 'xx')->exists())->toBeFalse();
});

it('lets a before hook change the input', function (): void {
    lynx()->hook(CreateLanguage::class)->before(fn (Input $input): Input => $input->with('name', mb_strtoupper((string) $input->string('name'))));

    expect(lynx()->languages->add('fr', 'Français')->name)->toBe('FRANÇAIS');
});

it('dispatches domain events only after commit and never on dry runs', function (): void {
    Event::fake([LanguageCreated::class]);

    lynx()->languages->add('es', 'Español', dryRun: true);
    Event::assertNotDispatched(LanguageCreated::class);

    lynx()->languages->add('es', 'Español');
    Event::assertDispatched(LanguageCreated::class, fn ($e): bool => $e->language->code === 'es');
});

it('records the actor', function (): void {
    lynx()->actingAs(Actor::mcp(7, ['client' => 'test']), fn () => lynx()->languages->add('de', 'Deutsch'));

    $event = ActivityEvent::query()->latest('id')->first();

    expect($event->actor_kind)->toBe('mcp')->and($event->actor_id)->toBe('7')->and($event->meta)->toBe(['client' => 'test']);
});

it('rolls back everything when an action fails', function (): void {
    expect(fn () => lynx()->languages->add('pt', 'Português', fallback: 'missing'))->toThrow(Exception::class)
        ->and(Language::query()->where('code', 'pt')->exists())->toBeFalse();
});
