<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Core\Facades\Lynx;
use Workbench\App\Http\DemoSignInController;
use Workbench\App\Http\SignInDemoUser;
use Workbench\App\Livewire\EnrolForm;
use Workbench\App\Models\User;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['auth.providers.users.model' => User::class]);

        // The admin always runs as a demo user (SignInDemoUser); /demo/sign-out shows the sign-in
        // screen at /lynx/login. Tests keep the default "auth" middleware.
        if (! $this->app->runningUnitTests()) {
            config([
                'lynx-admin.middleware' => ['web', SignInDemoUser::class],
            ]);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'workbench');
        $this->loadJsonTranslationsFrom(__DIR__.'/../../lang');
        $this->app['view']->addLocation(__DIR__.'/../../resources/views');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Livewire::component('enrol-form', EnrolForm::class);

        Route::middleware('web')->group(function (): void {
            Route::get('/demo/sign-in/{user}', [DemoSignInController::class, 'signIn']);
            Route::get('/demo/sign-out', [DemoSignInController::class, 'signOut']);
        });

        Lynx::components()->register(
            'enrol-form',
            'enrol-form',
            description: 'Course enrolment form. Stores enrolments in the app\'s own table.',
            // Settings are shared by every language (ADR-0021), so they hold configuration only;
            // the form's texts come from Laravel translations (workbench/lang).
            settings: fn (JsonSchema $s): array => [
                'capacity' => $s->integer()->description('Most enrolments accepted; the form closes when it is reached.'),
            ],
            rules: ['capacity' => ['sometimes', 'integer', 'min:1', 'max:1000']],
        );
    }
}
