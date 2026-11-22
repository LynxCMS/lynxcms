<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;

/*
 * Lynx's own wording in a language it ships no files for (ADR-0097): lines missing there read as
 * the app's fallback language, and English where that has none either. The app's own lines keep
 * Laravel's rules.
 */

beforeEach(function (): void {
    courseSite();
    $this->lang = testingPath('wording-lang');
    File::ensureDirectoryExists($this->lang);
    app('translation.loader')->addPath($this->lang);
});

afterEach(function (): void {
    File::deleteDirectory($this->lang);
});

/** Lines an app or a community translation adds over a Lynx namespace. */
function wordingLines(string $namespace, string $locale, string $group, string $php): void
{
    File::ensureDirectoryExists(test()->lang."/vendor/{$namespace}/{$locale}");
    File::put(test()->lang."/vendor/{$namespace}/{$locale}/{$group}.php", "<?php return {$php};");
}

function english(string $key): string
{
    return __($key, [], 'en');
}

it('reads English where neither the language nor the app\'s fallback has the line', function (string $locale, string $fallback): void {
    app()->setFallbackLocale($fallback);
    app()->setLocale($locale);

    expect(__('lynx-admin::home.title'))->toBe(english('lynx-admin::home.title'))
        ->and(__('lynx-finder::admin.title'))->toBe(english('lynx-finder::admin.title'));
})->with([
    'a language Lynx lacks' => ['et', 'et'],
    'the admin on a French site' => ['fr-CA', 'fr-CA'],
    'two languages Lynx lacks' => ['et', 'fi'],
]);

it('keeps the lines the language has', function (): void {
    wordingLines('lynx-admin', 'et', 'home', "['title' => 'Avaleht']");
    app()->setFallbackLocale('et');
    app()->setLocale('et');

    expect(__('lynx-admin::home.title'))->toBe('Avaleht')
        ->and(__('lynx-admin::home.today'))->toBe(english('lynx-admin::home.today'));
});

it('prefers the app\'s fallback language to English', function (): void {
    app()->setFallbackLocale('pt');
    app()->setLocale('et');

    expect(__('lynx-admin::home.title'))->toBe(__('lynx-admin::home.title', [], 'pt'));
});

it('reads an app\'s English wording, not only the shipped one', function (): void {
    wordingLines('lynx-admin', 'en', 'home', "['title' => 'Start here']");
    app()->setFallbackLocale('et');
    app()->setLocale('et');

    expect(__('lynx-admin::home.title'))->toBe('Start here');
});

it('picks English plurals for a language without plural forms', function (): void {
    app()->setFallbackLocale('en');
    app()->setLocale('ja');

    expect(trans_choice('lynx-admin::children.count', 5))->toBe(trans_choice('lynx-admin::children.count', 5, [], 'en'));
});

it('leaves the app\'s own lines to Laravel', function (): void {
    File::ensureDirectoryExists($this->lang.'/en');
    File::put($this->lang.'/en/shop.php', "<?php return ['cart' => 'Cart'];");
    app()->setFallbackLocale('et');
    app()->setLocale('et');

    expect(__('shop.cart'))->toBe('shop.cart');
});

it('shows no translation keys in the admin of a site in a language Lynx lacks', function (): void {
    config(['app.locale' => 'fr', 'lynx-admin.locale' => null]);
    app()->setFallbackLocale('fr');
    app()->setLocale('fr');
    $this->actingAs(new GenericUser(['id' => 1]));

    expect(preg_match('/lynx-[a-z]+::[a-z_.]+/', strip_tags((string) $this->get('/lynx')->assertOk()->getContent())))->toBe(0);
});
