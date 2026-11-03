<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use Workbench\App\Models\User;

/*
 * languages.set_current switches the request's language; with remember, the choice stays in the
 * session and on the signed-in user, so the next request and the next device start in it.
 */

beforeEach(function (): void {
    courseSite();
    $this->user = User::query()->forceCreate(['name' => 'Rita', 'email' => 'rita@example.com', 'password' => Hash::make('secret password')]);
    Route::post('set-language', function (): string {
        lynx()->run(SetCurrentLanguage::class, ['language' => 'pt', 'remember' => request()->boolean('remember')]);

        return lynx()->languages->current()->code;
    })->middleware('web');
});

it('remembers the language in the session and on the user', function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($this->user)
        ->post('/set-language', ['remember' => 1])
        ->assertOk()
        ->assertSee('pt')
        ->assertSessionHas(SetCurrentLanguage::SESSION_KEY, 'pt');

    expect($this->user->fresh()?->getAttribute('lynx_language_id'))->toBe(lynx()->languages->get('pt')->id);
});

it('switches for that request only without remember', function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($this->user)
        ->post('/set-language')
        ->assertOk()
        ->assertSee('pt')
        ->assertSessionMissing(SetCurrentLanguage::SESSION_KEY);

    expect($this->user->fresh()?->getAttribute('lynx_language_id'))->toBeNull();
});
