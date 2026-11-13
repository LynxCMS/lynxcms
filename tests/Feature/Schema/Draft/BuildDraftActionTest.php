<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Schema\BuildDraft;
use LynxCms\Core\Permissions\Role;

/*
 * The schema.draft action builds a draft for the MCP server and the agent (ADR-0116): the
 * database schema only, never files, after a dry run the agent shows to the person.
 */

beforeEach(fn () => courseSite());

const ACTION_DRAFT = "fields:\n  seats: integer min:1\ntemplates:\n  workshop:\n    fields: [title*, seats]\n";

function draftActor(int $id, array $permissions): Actor
{
    $role = Role::query()->create(['name' => "draft-{$id}", 'label' => "Draft {$id}"]);
    array_map(fn (string $permission) => $role->grants()->create(['permission' => $permission]), $permissions);
    $role->assign($id);

    return Actor::user($id);
}

it('returns the plan and changes nothing on a dry run', function (): void {
    $result = lynx()->run(BuildDraft::class, ['draft' => ACTION_DRAFT], dryRun: true);

    expect($result->value['plan'])->toBe(['create field seats', 'create template workshop'])
        ->and(lynx()->fields->find('seats'))->toBeNull();
});

it('builds the schema and writes no files', function (): void {
    $views = testingPath('draft-action-views');
    config(['view.paths' => [$views]]);

    lynx()->run(BuildDraft::class, ['draft' => ACTION_DRAFT]);

    expect(lynx()->templates->fields('workshop')->keys()->all())->toBe(['title', 'seats'])
        ->and(File::isDirectory($views))->toBeFalse();
});

it('refuses a draft with an error, saying what is wrong', function (): void {
    lynx()->run(BuildDraft::class, ['draft' => "fields:\n  seats: integr\n"]);
})->throws(ValidationException::class, 'Did you mean [integer]?');

it('needs schema.manage', function (): void {
    expect(fn (): mixed => lynx()->actingAs(draftActor(5, ['pages.edit']), fn (): ActionResult => lynx()->run(BuildDraft::class, ['draft' => ACTION_DRAFT])))
        ->toThrow(AuthorizationException::class, '[schema.manage]');
});

it('refuses a draft that leaves fields out of a template, unless prune_fields is given', function (): void {
    $draft = "templates:\n  course:\n    fields: [title*, summary]\n";

    expect(fn (): ActionResult => lynx()->run(BuildDraft::class, ['draft' => $draft]))
        ->toThrow(ValidationException::class, 'leaves out body, price, related');

    lynx()->run(BuildDraft::class, ['draft' => $draft, 'prune_fields' => true]);

    expect(lynx()->templates->fields('course')->keys()->all())->toBe(['title', 'summary']);
});

it('is offered as a tool', function (): void {
    expect(lynx()->actions()->find('schema.draft'))->toBe(BuildDraft::class);
});
