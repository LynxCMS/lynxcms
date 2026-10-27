<?php

declare(strict_types=1);

/*
 * The repeater and matrix examples on the Field Types page are the files in
 * docs/snippets/field-types, shown with VitePress includes. They run here on a fresh site.
 */

const FIELD_TYPES = __DIR__.'/../../../docs/snippets/field-types';

beforeEach(function (): void {
    installLynx();
});

it('runs the repeater example', function (): void {
    require FIELD_TYPES.'/repeater.php';

    expect(lynx()->fields->get('credits')->config()['fields'])->toBe([['name' => 'partner', 'required' => true], ['name' => 'role']]);
});

it('runs the matrix example, creating the fields of each block type first', function (): void {
    require FIELD_TYPES.'/matrix.php';

    expect(lynx()->fields->get('blocks')->fieldType()->slugs(lynx()->fields->get('blocks')->config()))->toBe(['text', 'video']);
});
