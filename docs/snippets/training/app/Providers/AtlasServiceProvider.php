<?php

declare(strict_types=1);

namespace App\Providers;

use App\Forms\EnquiryForm;
use App\Forms\NewsletterForm;
use App\Members\CourseMaterials;
use App\View\Composers\CourseCatalogue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\Visibility\PageVisibility;

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

        // #region members
        $materials = new CourseMaterials;
        app(PageVisibility::class)->add($materials);

        // Lists and search leave materials out; this guards their URL
        app(PageGates::class)->add(fn (Page $page) => match (true) {
            $materials->allows($page) => null,
            auth()->guest() => redirect()->guest(route('login')),
            default => response()->view('account.not-booked', ['page' => $page], 403),
        });
        // #endregion members

        // #region forms
        lynx()->forms()->register(EnquiryForm::class);
        lynx()->forms()->register(NewsletterForm::class);
        // #endregion forms
    }
}
