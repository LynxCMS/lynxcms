<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Models\Setting;
use LynxCms\Forms\Actions\SaveTurnstile;
use LynxCms\Forms\Admin\Livewire\TurnstileSettings;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Spam\Turnstile;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * Cloudflare Turnstile on public forms (ADR-0148): off until a site key and a secret are set, in
 * .env or by a superuser; then a submission is sent only once Cloudflare confirms its token.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    RateLimiter::clear('lynx-forms');
});

function turnstileQuote(): Testable
{
    $component = lynx()->language('pt', fn (): Testable => Livewire::test(FormComponent::class, ['form' => 'quote', 'pageId' => (int) lynx()->pages->get('/courses/')?->id]))
        ->set('data.type', 'film')->call('addRow', 'deliverables')->set('data.deliverables.0.format', 'Teaser')->call('next')
        ->set('data.name', 'Ana Lima')->set('data.email', 'ana@example.com')->set('data.consent', true);
    test()->travel(5)->seconds();

    return $component;
}

function turnstileKeys(): void
{
    config(['lynx-forms.turnstile' => ['site_key' => '1x00000000000000000000AA', 'secret' => '1x0000000000000000000000000000000AA']]);
}

it('stays off without keys, and forms send as before', function (): void {
    Http::fake();

    turnstileQuote()->assertDontSeeHtml('data-turnstile')->call('submit')->assertSet('isSent', true);
    Http::assertNothingSent();
});

it('shows the widget on the last step and sends once Cloudflare confirms the token', function (): void {
    turnstileKeys();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    turnstileQuote()->assertSeeHtml('data-turnstile')->set('turnstileToken', 'token-from-widget')->call('submit')
        ->assertSet('isSent', true)->assertDispatched('lynx-turnstile-reset');

    Http::assertSent(fn (Request $request): bool => $request['response'] === 'token-from-widget' && $request['secret'] === '1x0000000000000000000000000000000AA');
    expect(Submission::query()->count())->toBe(1);
});

it('refuses a submission Cloudflare does not confirm, or without a token', function (array $response, string $token): void {
    turnstileKeys();
    Http::fake(['challenges.cloudflare.com/*' => Http::response($response)]);

    turnstileQuote()->set('turnstileToken', $token)->call('submit')
        ->assertSet('isSent', false)->assertSet('problem', __('lynx-forms::forms.turnstile'));

    expect(Submission::query()->count())->toBe(0);
})->with([
    'refused' => [['success' => false, 'error-codes' => ['invalid-input-response']], 'bad-token'],
    'no token' => [['success' => true], ''],
]);

it('refuses a submission when Cloudflare cannot be reached', function (): void {
    turnstileKeys();
    Http::fake(['challenges.cloudflare.com/*' => fn (): never => throw new ConnectionException('timeout')]);

    turnstileQuote()->set('turnstileToken', 'token')->call('submit')->assertSet('isSent', false);
});

it('stores the keys a superuser saves, the secret encrypted, and keeps the secret when left empty', function (): void {
    lynx()->run(SaveTurnstile::class, ['site_key' => 'site-key', 'secret' => 'secret-key']);
    $stored = (string) Setting::read(Turnstile::SETTING);

    expect($stored)->not->toContain('secret-key')->and(app(Turnstile::class)->isEnabled())->toBeTrue();

    lynx()->run(SaveTurnstile::class, ['site_key' => 'other-site-key', 'secret' => '']);

    expect(app(Turnstile::class)->siteKey())->toBe('other-site-key')->and(app(Turnstile::class)->isEnabled())->toBeTrue();

    lynx()->run(SaveTurnstile::class, ['site_key' => '', 'secret' => '']);

    expect(app(Turnstile::class)->isEnabled())->toBeFalse();
});

it('needs a secret the first time', function (): void {
    lynx()->run(SaveTurnstile::class, ['site_key' => 'site-key', 'secret' => '']);
})->throws(ValidationException::class);

it('lets superusers set the keys on the Forms tab, unless .env sets them', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get(route('lynx.admin.forms.builder'))->assertOk()->assertSeeLivewire(TurnstileSettings::class);
    Livewire::test(TurnstileSettings::class)->assertSeeHtml('data-turnstile-state="off"')
        ->set('siteKey', 'site-key')->set('secret', 'secret-key')->call('save')->assertHasNoErrors()
        ->assertSet('secret', '')->assertSeeHtml('data-turnstile-state="on"')
        ->call('turnOff')->assertSeeHtml('data-turnstile-state="off"');

    turnstileKeys();
    Livewire::test(TurnstileSettings::class)->assertSeeHtml('data-turnstile-env')->call('save')->assertForbidden();
});
