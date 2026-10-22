<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Lynx;
use LynxCms\Core\Templates\FieldDisplay;

/*
 * ADR-0063: how a template's field shows in the page editor.
 */

beforeEach(fn (): Lynx => courseSite());

$field = fn (string $name) => lynx()->templates->fields('course')->get($name);

it('reads the editor settings of a template field', function () use ($field): void {
    lynx()->templates->update('course', ['add' => [
        ['name' => 'summary', 'editor' => 'collapsed_blank', 'width' => 50, 'placeholder' => ['default' => 'A line', 'pt' => 'Uma linha'], 'icon' => 'text'],
        ['name' => 'price', 'editor' => 'locked'],
    ]]);

    expect($field('summary')->editor())->toBe(FieldDisplay::CollapsedBlank)
        ->and($field('summary')->width())->toBe(50)
        ->and($field('summary')->placeholder())->toBe('A line')
        ->and(lynx()->language('pt', fn () => $field('summary')->placeholder()))->toBe('Uma linha')
        ->and($field('summary')->icon())->toBe('text')
        ->and($field('price')->editor())->toBe(FieldDisplay::Locked)
        ->and($field('price')->editor()->isEditable())->toBeFalse()
        ->and($field('body')->editor())->toBe(FieldDisplay::Open)
        ->and($field('body')->width())->toBe(100)
        ->and($field('body')->icon())->toBeNull();
});

it('refuses unknown editor settings', function (array $spec): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary'] + $spec]]);
})->with([
    'editor' => [['editor' => 'folded']],
    'width' => [['width' => 40]],
    'icon' => [['icon' => 'Not An Icon!']],
])->throws(ValidationException::class);

it('refuses required fields editors could not fill', function (string $editor, array $required): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'editor' => $editor] + $required]]);
})->with([
    'locked and required' => ['locked', ['required' => true]],
    'hidden and required' => ['hidden', ['required' => true]],
    'locked and required_if' => ['locked', ['required_if' => 'price>0']],
])->throws(ValidationException::class);

it('still writes locked and hidden fields through the API', function (): void {
    lynx()->templates->update('course', ['add' => [['name' => 'price', 'editor' => 'hidden']]]);
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'kept';
    $page->title = 'Kept';
    $page->price = 120;
    $page->save();

    expect(lynx()->pages->get('/courses/kept/')?->price)->toBe(120);
});
