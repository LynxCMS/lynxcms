<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Lynx;

beforeEach(fn (): Lynx => installLynx());

it('reports invalid type settings under settings.<name>', function (string $action): void {
    lynx()->fields->add('teaser', 'text');

    try {
        $action === 'create'
            ? lynx()->fields->add('intro', 'text', settings: ['max_length' => 9999])
            : lynx()->fields->update('teaser', ['settings' => ['max_length' => 9999]]);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('settings.max_length');
    }
})->with(['create', 'update']);

it('removes a setting given as null on update', function (): void {
    lynx()->fields->add('teaser', 'text', settings: ['max_length' => 120, 'placeholder' => 'Say it short']);

    lynx()->fields->update('teaser', ['settings' => ['placeholder' => null, 'max_length' => 100]]);

    expect(lynx()->fields->get('teaser')->config())->toMatchArray(['max_length' => 100])
        ->not->toHaveKey('placeholder');
});

it('keeps every setting when some of them have their own rules', function (): void {
    lynx()->fields->add('intro', 'textarea', translatable: true, settings: ['format' => 'html', 'max_length' => 500, 'description' => 'Shown first.', 'fallback' => 'none']);

    expect(lynx()->fields->get('intro')->config())->toMatchArray(['format' => 'html', 'max_length' => 500, 'description' => 'Shown first.', 'fallback' => 'none']);
});

it('names the templates a field is removed from when it is deleted', function (): void {
    lynx()->fields->add('teaser', 'text');
    lynx()->templates->add('article', fields: ['teaser']);
    lynx()->templates->add('news', fields: ['teaser']);

    $result = lynx()->fields->delete('teaser', dryRun: true);

    expect($result->changes->summary()[0])->toContain('removes it from templates article, news')
        ->and($result->changes->all()[0]->data['templates'])->toBe(['article', 'news'])
        ->and(lynx()->fields->find('teaser'))->not->toBeNull();
});
