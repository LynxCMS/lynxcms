<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FormValues;
use LynxCms\Tests\Fixtures\SpotlightConverter;

beforeEach(function (): void {
    View::addNamespace('fixtures', __DIR__.'/../../Fixtures/views');
    app(FieldInputs::class)->register('spotlight', 'lynx-admin::inputs.text', SpotlightConverter::class, 'fixtures::spotlight');
});

it('converts module values through the converter registered with their input', function (): void {
    $forms = app(FormValues::class);

    expect($forms->toForm('spotlight', ['note' => 'Hi', 'pages' => [1]]))->toBe('Hi')
        ->and($forms->fromForm('spotlight', 'Hello'))->toBe(['note' => 'Hello', 'pages' => []])
        ->and($forms->fromForm('unknown', ['x' => 1]))->toBe(['x' => 1]);
});

it('keeps the core conversions', function (): void {
    $forms = app(FormValues::class);

    expect($forms->fromForm('text', ''))->toBeNull()
        ->and($forms->fromForm('integer', ' 12 '))->toBe(12)
        ->and($forms->fromForm('integer', 'x'))->toBe('x')
        ->and($forms->fromForm('page', ['3', 3, '4']))->toBe([3, 4])
        ->and($forms->toForm('toggle', null))->toBeFalse();
});

it('shows module values with their registered display view', function (): void {
    expect(Blade::render('<x-lynx-admin::editor.value type="spotlight" value="LIGHT" />'))->toContain('Spotlight: LIGHT')
        ->and(Blade::render('<x-lynx-admin::editor.value type="toggle" :value="true" />'))->toContain('Yes');
});
