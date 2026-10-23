<?php

declare(strict_types=1);

use App\Providers\AtlasServiceProvider;
use Database\Seeders\AtlasContentSeeder;
use Database\Seeders\AtlasSchemaSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
 * Boots the training site guide's app (docs/snippets/training) inside the test app: its classes,
 * views, schema and demo content. Required by every test of the guide.
 */

const TRAINING = __DIR__.'/../../../../docs/snippets/training';

require_once TRAINING.'/database/seeders/AtlasSchemaSeeder.php';
require_once TRAINING.'/database/seeders/AtlasContentSeeder.php';
require_once TRAINING.'/app/View/Composers/CourseCatalogue.php';
require_once TRAINING.'/app/Forms/EnquiryForm.php';
require_once TRAINING.'/app/Forms/NewsletterForm.php';
require_once TRAINING.'/app/Listeners/SendEnquiryToCrm.php';
require_once TRAINING.'/app/Listeners/SubscribeToNewsletter.php';
require_once TRAINING.'/app/Models/Booking.php';
require_once TRAINING.'/app/Bookings/StripeCheckout.php';
require_once TRAINING.'/app/Bookings/StripeSignature.php';
require_once TRAINING.'/app/Bookings/Seats.php';
require_once TRAINING.'/app/Mail/BookingConfirmed.php';
require_once TRAINING.'/app/Http/Controllers/BookingController.php';
require_once TRAINING.'/app/Http/Controllers/StripeWebhookController.php';
require_once TRAINING.'/app/Members/CourseMaterials.php';
require_once TRAINING.'/app/Http/Controllers/AccountController.php';
require_once TRAINING.'/app/Providers/AtlasServiceProvider.php';

/** The schema, and the demo content unless $withContent is false. */
function trainingSite(bool $withContent = true): void
{
    installLynx();
    new AtlasSchemaSeeder()->run();

    if ($withContent) {
        new AtlasContentSeeder()->run();
    }

    new AtlasServiceProvider(app())->boot();
    View::prependLocation(TRAINING.'/resources/views');
    View::getFinder()->flush();
    trainingApp();
}

/** The app's own routes and the bookings table, for the chapters that use them. */
function trainingApp(): void
{
    (require TRAINING.'/database/migrations/2026_10_24_000000_create_bookings_table.php')->up();
    require TRAINING.'/routes/web.php';
    Route::getRoutes()->refreshNameLookups();
    app('translator')->addJsonPath(TRAINING.'/lang');
}
