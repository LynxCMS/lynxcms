<?php

declare(strict_types=1);

use App\Providers\AtlasServiceProvider;
use Database\Seeders\AtlasContentSeeder;
use Database\Seeders\AtlasSchemaSeeder;
use Illuminate\Support\Facades\View;

/*
 * Boots the training site guide's app (docs/snippets/training) inside the test app: its classes,
 * views, schema and demo content. Required by every test of the guide.
 */

const TRAINING = __DIR__.'/../../../../docs/snippets/training';

require_once TRAINING.'/database/seeders/AtlasSchemaSeeder.php';
require_once TRAINING.'/database/seeders/AtlasContentSeeder.php';
require_once TRAINING.'/app/View/Composers/CourseCatalogue.php';
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
}
