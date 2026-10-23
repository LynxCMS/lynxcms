<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Seo\Actions\MapTemplate;
use LynxCms\Seo\Actions\UnmapTemplate;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\SeoSchema;

/*
 * seo.map_template and seo.unmap_template: structured data mappings from code, MCP and the
 * agent, checked like the admin's (MappingValidator), one template at a time.
 */

beforeEach(function (): void {
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    createCourses('alpha');
});

/** @return array<string, array{enabled: bool, template: string, type: string, lines: string}> */
function mappingsByTemplate(): array
{
    return collect(Mappings::items(lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)))->keyBy('template')->all();
}

it('maps a template to a schema.org type, and the page shows it', function (): void {
    lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => '@title', 'provider' => '@organization']]);

    $graph = lynx()->seo(lynx()->pages->get('/courses/alpha/'))->jsonLd['@graph'];

    expect(collect($graph)->firstWhere('@type', 'Course')['name'] ?? null)->toBe('Alpha')
        ->and(mappingsByTemplate()['course']['lines'])->toBe("name: @title\nprovider: @organization");
});

it('replaces the template\'s mapping and keeps the others', function (): void {
    lynx()->run(MapTemplate::class, ['template' => 'courses', 'type' => 'CreativeWork', 'map' => ['name' => '@title']]);
    lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => '@title']]);
    lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Event', 'map' => ['name' => '@title']]);

    expect(array_map(fn (array $m): string => $m['type'], mappingsByTemplate()))->toBe(['courses' => 'CreativeWork', 'course' => 'Event']);
});

it('refuses a mapping that would make broken structured data', function (): void {
    expect(fn (): ActionResult => lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => 'no_such_field']]))
        ->toThrow(ValidationException::class);

    expect(fn (): ActionResult => lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Spaceship', 'map' => ['name' => '@title']]))
        ->toThrow(ValidationException::class);

    expect(mappingsByTemplate())->toBe([]);
});

it('previews a mapping as a dry run, writing nothing', function (): void {
    $result = lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => '@title']], dryRun: true);

    expect($result->changes->summary())->toContain('+ Map template [course] to Course')
        ->and(mappingsByTemplate())->toBe([]);
});

it('removes a template\'s mapping', function (): void {
    lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => '@title']]);
    lynx()->run(UnmapTemplate::class, ['template' => 'course']);

    expect(mappingsByTemplate())->toBe([]);
});

it('needs seo.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(5), fn (): ActionResult => lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => ['name' => '@title']])))
        ->toThrow(AuthorizationException::class);
});

it('is a tool for MCP clients and the agent', function (): void {
    expect(lynx()->actions()->find('seo.map_template'))->toBe(MapTemplate::class)
        ->and(lynx()->actions()->find('seo.unmap_template'))->toBe(UnmapTemplate::class);
});
