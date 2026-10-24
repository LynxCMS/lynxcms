<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Events\LanguageUpdated;
use LynxCms\Core\Lynx;
use LynxCms\Core\Models\Language;

beforeEach(fn (): Lynx => installLynx());

it('installs a default language with an empty prefix', function (): void {
    $default = lynx()->languages->default();

    expect($default->code)->toBe('default')
        ->and($default->name)->toBe('Default')
        ->and($default->url_prefix)->toBe('')
        ->and($default->locale)->toBeNull()
        ->and(lynx()->pages->get('/'))->not->toBeNull();
});

it('is idempotent', function (): void {
    installLynx();

    // The home page and the page not found.
    expect(Language::query()->count())->toBe(1)->and(DB::table('lynx_pages')->count())->toBe(2);
});

it('adds languages at runtime without changing the schema', function (): void {
    $snapshot = fn () => collect(Schema::getTables())->pluck('name')->sort()->values()->all();
    $before = $snapshot();

    $pt = lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');

    expect($pt->url_prefix)->toBe('pt')
        ->and($pt->appLocale())->toBe('pt-PT')
        ->and($snapshot())->toBe($before);
});

it('serves the home page in a language added after install', function (): void {
    lynx()->languages->add('pt', 'Português', fallback: 'default');

    expect(lynx()->pages->get('/')?->localUrl('pt'))->toBe('/pt');
    $this->get('/pt')->assertOk();
});

it('builds fallback chains ending with the default language', function (): void {
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
    lynx()->languages->add('es', 'Español');

    $codes = fn (string $code): array => array_map(fn (Language $l) => $l->code, lynx()->languages->chain($code));

    expect($codes('pt-br'))->toBe(['pt-br', 'pt', 'default'])
        ->and($codes('es'))->toBe(['es', 'default'])
        ->and($codes('default'))->toBe(['default']);
});

it('rejects fallback cycles', function (): void {
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');

    expect(fn () => lynx()->languages->update('pt', ['fallback' => 'pt-br']))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->languages->update('default', ['fallback' => 'pt-br']))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->languages->update('pt', ['fallback' => 'pt']))->toThrow(ValidationException::class);
});

it('validates codes and prefixes', function (): void {
    lynx()->languages->add('pt', 'Português');

    expect(fn () => lynx()->languages->add('pt', 'Again'))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->languages->add('PT BR', 'Bad'))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->languages->add('br', 'Brasil', prefix: 'pt'))->toThrow(ValidationException::class);
});

it('renames the default language', function (): void {
    lynx()->languages->update('default', ['name' => 'English', 'locale' => 'en']);

    expect(lynx()->languages->default()->name)->toBe('English')->and(lynx()->languages->default()->appLocale())->toBe('en');
});

it('never deletes the default language', function (): void {
    expect(fn () => lynx()->languages->delete('default'))->toThrow(ValidationException::class);
});

it('deletes a language and points its dependants at the default', function (): void {
    lynx()->languages->add('pt', 'Português');
    lynx()->languages->add('pt-br', 'Brasil', fallback: 'pt');

    lynx()->languages->delete('pt');

    expect(lynx()->languages->find('pt'))->toBeNull()
        ->and(lynx()->languages->get('pt-br')->fallback_id)->toBeNull();
});

it('sets the current language and Laravel locale for a closure', function (): void {
    lynx()->languages->add('pt', 'Português', locale: 'pt-PT');

    $inside = lynx()->language('pt', fn (): array => [lynx()->languages->current()->code, app()->getLocale()]);

    expect($inside)->toBe(['pt', 'pt-PT'])->and(lynx()->languages->current()->code)->toBe('default');
});

it('announces a language update after it commits, and only when something changed', function (): void {
    $lynx = lynx();
    $lynx->languages->add('pt', 'Português', fallback: 'default');
    Event::fake([LanguageUpdated::class]);

    $lynx->languages->update('pt', ['name' => 'Portuguese']);
    $lynx->languages->update('pt', ['name' => 'Portuguese']);

    Event::assertDispatchedTimes(LanguageUpdated::class, 1);
});

it('says which columns a language update changed', function (): void {
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->languages->add('es', 'Español', fallback: 'default');
    Event::fake([LanguageUpdated::class]);

    lynx()->languages->update('es', ['name' => 'Spanish']);
    lynx()->languages->update('es', ['fallback' => 'pt']);

    Event::assertDispatched(LanguageUpdated::class, fn (LanguageUpdated $event): bool => $event->changed === ['name'] && ! $event->changedFallback());
    Event::assertDispatched(LanguageUpdated::class, fn (LanguageUpdated $event): bool => $event->changedFallback());
});
