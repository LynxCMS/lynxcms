<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Branding\Branding;
use LynxCms\Admin\Branding\SaveBranding;
use LynxCms\Admin\Livewire\BrandingForm;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Runner\ActionResult;

/*
 * The site's name, logo and colours on the admin's sign-in screens and sidebar (ADR-0084):
 * saved by superusers under Settings > General, over config('lynx-admin.branding').
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
});

/** A one-pixel PNG as a data URL. */
function brandingPngLogo(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
}

it('saves the name, colours and logo, and serves the logo', function (): void {
    lynx()->run(SaveBranding::class, ['name' => 'Castor & Otters', 'background' => '#F3EEE4', 'accent' => '#b4602c', 'logo' => brandingPngLogo()]);
    $branding = Branding::current();

    expect($branding->name)->toBe('Castor & Otters')
        ->and($branding->background)->toBe('#f3eee4')
        ->and($branding->accent)->toBe('#b4602c')
        ->and($branding->uploadedLogo)->toMatch(Branding::LOGO_FILE)
        ->and(Storage::disk('local')->exists('lynx-admin/'.$branding->uploadedLogo))->toBeTrue();

    $this->get((string) $branding->logoUrl())->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('keeps keys left out, and goes back to the config default for empty ones', function (): void {
    config(['lynx-admin.branding.accent' => '#123456']);
    lynx()->run(SaveBranding::class, ['name' => 'Castor & Otters', 'accent' => '#b4602c']);
    lynx()->run(SaveBranding::class, ['accent' => '']);

    expect(Branding::current()->name)->toBe('Castor & Otters')
        ->and(Branding::current()->accent)->toBe('#123456');
});

it('replaces and removes the uploaded logo, deleting the old file', function (): void {
    lynx()->run(SaveBranding::class, ['logo' => brandingPngLogo()]);
    $first = (string) Branding::current()->uploadedLogo;
    $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
    lynx()->run(SaveBranding::class, ['logo' => $svg]);
    $second = (string) Branding::current()->uploadedLogo;

    expect($second)->toEndWith('.svg')
        ->and(Storage::disk('local')->exists('lynx-admin/'.$first))->toBeFalse();
    $this->get((string) Branding::current()->logoUrl())->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");

    lynx()->run(SaveBranding::class, ['remove_logo' => true]);

    expect(Branding::current()->uploadedLogo)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses colours that are not hex, and files that are not images', function (string $key, string $value): void {
    expect(fn (): ActionResult => lynx()->run(SaveBranding::class, [$key => $value]))->toThrow(ValidationException::class);
})->with([
    'a colour name' => ['accent', 'red'],
    'CSS in a colour' => ['background', '#fff; background: url(x)'],
    'a script as a logo' => ['logo', 'data:image/png;base64,'.base64_encode('<script>alert(1)</script>')],
    'not a data URL' => ['logo', 'https://example.com/logo.png'],
]);

it('writes nothing on a dry run, and nothing when it rolls back', function (): void {
    $result = lynx()->run(SaveBranding::class, ['name' => 'Dry', 'logo' => brandingPngLogo()], dryRun: true);

    expect($result->changes->all())->not->toBeEmpty()
        ->and(Branding::saved())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    lynx()->hook(SaveBranding::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(SaveBranding::class, ['name' => 'Halted', 'logo' => brandingPngLogo()]))->toThrow(ActionHalted::class)
        ->and(Branding::saved())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('shows the logo on the sign-in screen and in the sidebar, with the colours', function (): void {
    lynx()->run(SaveBranding::class, ['name' => 'Castor & Otters', 'background' => '#f3eee4', 'accent' => '#1f5a69', 'logo' => brandingPngLogo()]);
    $logo = (string) Branding::current()->logoUrl();

    $this->get(route('login'))->assertOk()
        ->assertSee('src="'.$logo.'"', false)
        ->assertSee('background-color: #f3eee4; color: #111111;', false)
        ->assertSee('--primary: #1f5a69; --primary-foreground: #ffffff;', false);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')->assertOk()->assertSee('src="'.$logo.'"', false);
});

it('answers only for logo files, and only superusers open Settings > General', function (): void {
    $this->get('/lynx/assets/logo/logo-0000000000000000.png')->assertNotFound();
    $this->get('/lynx/assets/logo/..%2F..%2F.env')->assertNotFound();

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/general')->assertOk();
    Livewire::actingAs(new GenericUser(['id' => 1]))->test(BrandingForm::class)
        ->set('accent', '#b4602c')->call('save')->assertHasNoErrors();

    expect(Branding::current()->accent)->toBe('#b4602c');
});

it('keeps the admin\'s own look without branding', function (): void {
    expect(Branding::current()->logoUrl())->toBeNull()
        ->and(Branding::current()->accentStyle())->toBe('');
});

it('is closed to anyone but superusers', function (): void {
    expect(SaveBranding::permission())->toBeNull();
});
