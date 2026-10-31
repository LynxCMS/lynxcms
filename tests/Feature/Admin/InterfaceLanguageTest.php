<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Http\UseAdminLocale;
use LynxCms\Admin\Locale\AdminLocale;
use LynxCms\Admin\Locale\SaveAdminLocale;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Models\Setting;

/*
 * The language of the admin's own wording (ADR-0097): each editor's choice, then
 * config('lynx-admin.locale'), then the app's locale. Site languages play no part in it.
 */

beforeEach(function (): void {
    courseSite();
    config(['lynx-admin.locales' => ['en' => 'English', 'pt' => 'Português']]);
});

/** Runs the action as a signed-in user, as the admin's controller does. */
function saveAdminLocale(mixed $locale, int|string $userId = 1, bool $dryRun = false): mixed
{
    return app(ActionRunner::class)->actingAs(
        Actor::user($userId),
        fn (): mixed => app(ActionRunner::class)->run(SaveAdminLocale::class, ['locale' => $locale], dryRun: $dryRun),
    );
}

it('follows the app locale until something else is set', function (): void {
    config(['app.locale' => 'pt', 'lynx-admin.locale' => null]);

    expect(AdminLocale::forUser(null))->toBe('pt');
});

it('prefers the admin default over the app locale', function (): void {
    config(['app.locale' => 'en', 'lynx-admin.locale' => 'pt']);

    expect(AdminLocale::forUser(null))->toBe('pt');
});

it('prefers an editor\'s choice over the admin default', function (): void {
    config(['lynx-admin.locale' => 'en']);
    saveAdminLocale('pt');

    expect(AdminLocale::forUser(1))->toBe('pt')
        ->and(AdminLocale::forUser(2))->toBe('en');
});

it('goes back to the default when an editor clears the choice', function (): void {
    config(['lynx-admin.locale' => 'en']);
    saveAdminLocale('pt');
    saveAdminLocale('');

    expect(AdminLocale::forUser(1))->toBe('en')
        ->and(Setting::read(AdminLocale::setting(1)))->toBeNull();
});

it('ignores a saved choice the site no longer offers', function (): void {
    config(['lynx-admin.locale' => 'en']);
    saveAdminLocale('pt');
    config(['lynx-admin.locales' => ['en' => 'English']]);

    expect(AdminLocale::forUser(1))->toBe('en');
});

it('refuses a language the site does not offer', function (): void {
    saveAdminLocale('de');
})->throws(ValidationException::class);

it('refuses an actor that is not a signed-in user', function (): void {
    app(ActionRunner::class)->actingAs(Actor::cli(), fn (): mixed => app(ActionRunner::class)->run(SaveAdminLocale::class, ['locale' => 'pt']));
})->throws(ValidationException::class);

it('saves nothing on a dry run', function (): void {
    saveAdminLocale('pt', dryRun: true);

    expect(Setting::read(AdminLocale::setting(1)))->toBeNull();
});

it('keeps a personal choice away from agents and the activity log', function (): void {
    expect(SaveAdminLocale::exposedAsTool())->toBeFalse()
        ->and(SaveAdminLocale::logsActivity())->toBeFalse();
});

it('renders the admin in the signed-in editor\'s language', function (): void {
    saveAdminLocale('pt');

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertOk()->assertSee('lang="pt"', false);
    $this->actingAs(new GenericUser(['id' => 2]))->get('/lynx/pages')->assertOk()->assertSee('lang="en"', false);
});

it('renders the sign-in screen in the admin default', function (): void {
    config(['lynx-admin.locale' => 'pt']);

    $this->get('/lynx/login')->assertOk()->assertSee('lang="pt"', false);
});

it('keeps the language in Livewire updates', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(UseAdminLocale::class);
});

it('leaves the site itself alone', function (): void {
    config(['lynx-admin.locale' => 'pt']);

    $this->get('/courses/')->assertOk()->assertSee('lang="en"', false);
});

it('lets an editor change language from the admin', function (): void {
    $user = new GenericUser(['id' => 1]);

    $this->actingAs($user)->from('/lynx/pages')->post('/lynx/locale', ['locale' => 'pt'])->assertRedirect('/lynx/pages');

    expect(AdminLocale::forUser(1))->toBe('pt');
});

it('offers the choice only when there is one', function (bool $hasChoice): void {
    config(['lynx-admin.locales' => $hasChoice ? ['en' => 'English', 'pt' => 'Português'] : ['en' => 'English']]);

    $response = $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertOk();

    expect(str_contains((string) $response->getContent(), 'data-lynx-locale'))->toBe($hasChoice)
        ->and(str_contains((string) $response->getContent(), 'value="pt"'))->toBe($hasChoice);
})->with(['two languages' => true, 'one language' => false]);
