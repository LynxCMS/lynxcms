<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Schema\Draft\DraftJsonSchema;
use LynxCms\Core\Schema\Draft\DraftTemplates;
use Symfony\Component\Yaml\Yaml;

/*
 * lynx:draft --json-schema writes a JSON Schema of the draft format (ADR-0116), built from the
 * registered field types, so editors validate and complete a draft as it is typed.
 */

/** @return array<string, mixed> */
function draftJsonSchema(): array
{
    return json_decode(app(DraftJsonSchema::class)->toJson(), true, flags: JSON_THROW_ON_ERROR);
}

it('describes a draft as fields and templates, and nothing else', function (): void {
    expect(draftJsonSchema())->toMatchArray(['type' => 'object', 'additionalProperties' => false])
        ->and(array_keys(draftJsonSchema()['properties']))->toBe(['fields', 'templates']);
});

it('lists every registered field type with its settings in the long form', function (): void {
    $long = collect(draftJsonSchema()['$defs']['longField']['anyOf'])->keyBy(fn (array $branch): string => $branch['properties']['type']['const']);

    expect($long->keys()->sort()->values()->all())->toBe(array_keys(app(FieldTypeRegistry::class)->all()))
        ->and(array_keys($long['decimal']['properties']))->toContain('places', 'min', 'max', 'label', 'translatable', 'fallback');
});

it('gives the template keys a draft accepts', function (): void {
    expect(array_keys(draftJsonSchema()['$defs']['template']['properties']))->toBe(DraftTemplates::KEYS);
});

it('accepts every short field of the starting draft', function (): void {
    $pattern = '/'.str_replace('/', '\/', draftJsonSchema()['$defs']['shortField']['pattern']).'/u';
    $stub = Yaml::parseFile(__DIR__.'/../../../../packages/core/resources/stubs/draft.yaml');

    foreach ($stub['fields'] as $name => $definition) {
        expect(preg_match($pattern, $definition))->toBe(1, "{$name}: {$definition}");
    }

    expect(preg_match($pattern, 'decmal:2'))->toBe(0);
});

it('writes the schema beside the draft with lynx:draft --json-schema', function (): void {
    $dir = testingPath('draft-json');
    File::deleteDirectory($dir);

    $this->artisan('lynx:draft', ['path' => $dir.'/draft.yaml', '--json-schema' => true])
        ->expectsOutputToContain('the YAML extension in VS Code, or PhpStorm')
        ->expectsOutputToContain('# yaml-language-server: $schema=./draft.schema.json')
        ->assertSuccessful();

    expect(json_decode(File::get($dir.'/draft.schema.json'), true))->toHaveKey('$defs');
    File::deleteDirectory($dir);
});

it('names each type\'s argument in the short form\'s description', function (): void {
    expect(draftJsonSchema()['$defs']['shortField']['description'])->toContain('decimal:places', 'choice:options');
});
