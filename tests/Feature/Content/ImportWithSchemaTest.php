<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Support\Tables;

/*
 * lynx:import --with-schema sets up a new environment, or restores a backup, from one package:
 * the schema, then the pages, then the roles, whose grants may be limited to branches of those
 * pages. It all succeeds or nothing is written, dry runs included.
 */

/** The course site fixture, with a role limited to the branch $branch. */
function packageWithBranchRole(string $branch): string
{
    $directory = testingPath('import-with-schema');
    File::deleteDirectory($directory);
    File::copyDirectory(__DIR__.'/../../Fixtures/content/course-site', $directory);
    File::put($directory.'/schema/roles/course-editor.yaml', <<<YAML
        name: course-editor
        label: 'Course editor'
        superuser: false
        grants:
          -
            permission: pages.edit
            branches:
              - {$branch}
        YAML);

    return $directory;
}

it('imports a package whose roles are limited to branches of its pages', function (): void {
    $this->artisan('lynx:import', ['path' => packageWithBranchRole('/courses/'), '--with-schema' => true])
        ->expectsOutputToContain('3 created')
        ->assertSuccessful();

    $grant = Role::query()->where('name', 'course-editor')->sole()->grants->sole();

    expect($grant->branches)->toBe([(int) lynx()->pages->get('/courses/')?->id])
        ->and(lynx()->languages->find('pt'))->not->toBeNull();
});

it('imports the same package into a site that ran lynx:install', function (): void {
    installLynx();

    $this->artisan('lynx:import', ['path' => packageWithBranchRole('/courses/'), '--with-schema' => true])
        ->assertSuccessful();

    expect((string) lynx()->pages->get('/courses/alpha/')?->title)->toBe('Alpha')
        ->and(Role::query()->where('name', 'course-editor')->exists())->toBeTrue();
});

it('previews the import as a dry run, writing nothing', function (): void {
    $this->artisan('lynx:import', ['path' => packageWithBranchRole('/courses/'), '--with-schema' => true, '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Language::query()->count())->toBe(0)
        ->and(DB::table(Tables::PAGES)->count())->toBe(0)
        ->and(Role::query()->where('name', 'course-editor')->exists())->toBeFalse();
});

it('stops with the schema errors and writes nothing', function (): void {
    $this->artisan('lynx:import', ['path' => packageWithBranchRole('/nowhere/'), '--with-schema' => true])
        ->expectsOutputToContain('does not exist here')
        ->assertFailed();

    expect(Language::query()->count())->toBe(0)
        ->and(DB::table(Tables::PAGES)->count())->toBe(0);
});
