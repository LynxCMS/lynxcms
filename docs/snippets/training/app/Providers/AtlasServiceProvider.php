<?php

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\CourseCatalogue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Atlas Training's wiring. Each chapter of the guide adds its part here.
 */
final class AtlasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // #region views
        View::composer('templates.courses', CourseCatalogue::class);
        // #endregion views
    }
}
