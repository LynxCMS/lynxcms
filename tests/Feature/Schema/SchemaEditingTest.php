<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CleanHtmlValues;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\DuplicateField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Languages\DeleteLanguage;
use LynxCms\Core\Actions\Languages\UpdateLanguage;
use LynxCms\Core\Actions\Pages\PurgeOrphanedValues;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Actions\Templates\CopySection;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Lynx;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Schema\SchemaEditing;

/*
 * lynx.schema_editing off means the site's schema comes from files (ADR-0117): every action that
 * changes the schema refuses people, MCP clients and the agent, at any depth, while the command
 * line (lynx:schema:apply, lynx:install) and the system still change it.
 */

beforeEach(fn (): Lynx => courseSite());

final class AddsAFieldInside extends Action
{
    public static function name(): string
    {
        return 'testing.adds_a_field';
    }

    public static function permission(): string
    {
        return self::EVERYONE;
    }

    public static function description(): string
    {
        return 'Adds a field through a nested action.';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Input $input, ActionContext $context): mixed
    {
        return $context->run(CreateField::class, ['name' => 'nested', 'type' => 'text']);
    }
}

function schemaManager(int $id): void
{
    $role = Role::query()->create(['name' => "schema-{$id}", 'label' => "Schema {$id}"]);
    $role->grants()->create(['permission' => 'schema.manage']);
    $role->assign($id);
}

it('allows schema editing by default', function (): void {
    expect(app(SchemaEditing::class)->isEnabled())->toBeTrue();
});

it('refuses a schema change by a person, an MCP client or the agent when schema editing is off', function (Actor $actor): void {
    schemaManager(7);
    config(['lynx.schema_editing' => false]);

    expect(fn (): mixed => lynx()->actingAs($actor, fn (): mixed => lynx()->run(CreateField::class, ['name' => 'seats', 'type' => 'integer'])))
        ->toThrow(AuthorizationException::class, 'schema editing is turned off');
    expect(lynx()->fields->find('seats'))->toBeNull();
})->with([
    'a person' => fn (): Actor => Actor::user(7),
    'an MCP client' => fn (): Actor => Actor::mcp(7),
    'the agent' => fn (): Actor => Actor::ai(7),
]);

it('refuses a schema change nested in an action that changes none itself', function (): void {
    config(['lynx.schema_editing' => false]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(7), fn (): mixed => lynx()->run(AddsAFieldInside::class)))
        ->toThrow(AuthorizationException::class, 'schema editing is turned off');
});

it('lets the command line and the system change the schema when schema editing is off', function (Actor $actor): void {
    config(['lynx.schema_editing' => false]);

    lynx()->actingAs($actor, fn (): mixed => lynx()->run(CreateField::class, ['name' => 'seats', 'type' => 'integer']));

    expect(lynx()->fields->find('seats'))->not->toBeNull();
})->with([
    'the command line' => [Actor::cli()],
    'the system' => [Actor::system()],
]);

it('leaves actions that need schema.manage but change no schema alone', function (): void {
    schemaManager(7);
    config(['lynx.schema_editing' => false]);

    lynx()->actingAs(Actor::user(7), fn (): mixed => lynx()->run(RebuildSearchIndex::class));
})->throwsNoExceptions();

it('marks the actions that change the schema, and only those', function (): void {
    $changing = [CreateField::class, UpdateField::class, DeleteField::class, DuplicateField::class, CreateTemplate::class, UpdateTemplate::class, DeleteTemplate::class, CopySection::class, CreateLanguage::class, UpdateLanguage::class, DeleteLanguage::class];
    $other = [RebuildSearchIndex::class, PurgeOrphanedValues::class, CleanHtmlValues::class, AddsAFieldInside::class];

    expect(array_filter($changing, fn (string $action): bool => ! $action::changesSchema()))->toBe([])
        ->and(array_filter($other, fn (string $action): bool => $action::changesSchema()))->toBe([]);
});

it('says which templates and fields a person may edit', function (): void {
    lynx()->menus()->create('top', 'Top');
    $editing = app(SchemaEditing::class);

    expect($editing->canEditTemplate('course'))->toBeTrue()
        ->and($editing->canEditTemplate('menu-item'))->toBeFalse()
        ->and($editing->canEditField('menu_page'))->toBeFalse();

    config(['lynx.schema_editing' => false]);

    expect($editing->canEditTemplate('course'))->toBeFalse()
        ->and($editing->canEditField('summary'))->toBeFalse();
});
