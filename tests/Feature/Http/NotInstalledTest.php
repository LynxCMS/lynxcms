<?php

declare(strict_types=1);

// No courseSite(): an app that added Lynx but has not run lynx:install yet.
it('answers 404 for unknown URLs before Lynx is installed', function (): void {
    $this->get('/anything')->assertNotFound();
});
