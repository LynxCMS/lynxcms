<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Fields\Choices\ChoiceList;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('level', 'choice', settings: ['options' => [
        ['label' => ['default' => 'Beginner', 'pt' => 'Iniciante']],
        ['value' => 'advanced', 'label' => ['default' => 'Advanced', 'pt' => 'Avançado']],
    ]]);
    lynx()->fields->add('formats', 'choice', settings: ['multiple' => true, 'options' => [
        ['value' => 'online', 'label' => 'Online'],
        ['value' => 'onsite', 'label' => 'On site'],
        ['value' => 'hybrid', 'label' => 'Hybrid'],
    ]]);
    lynx()->templates->update('course', ['add' => ['level', 'formats']]);
});

/** A course with a level and formats. */
function choiceCourse(string $name, mixed $level, mixed $formats = []): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->level = $level;
    $page->formats = $formats;
    $page->save();

    return (int) $page->id;
}

it('makes option values from the labels and keeps labels per language', function (): void {
    $options = lynx()->fields->get('level')->config()['options'];

    expect(array_column($options, 'value'))->toBe(['beginner', 'advanced']);
});

it('refuses options with duplicate values', function (): void {
    lynx()->fields->add('size', 'choice', settings: ['options' => [['value' => 's', 'label' => 'S'], ['value' => 's', 'label' => 'Small']]]);
})->throws(ValidationException::class);

it('reads one choice with its label in the current language', function (): void {
    $page = lynx()->pages->get(choiceCourse('alpha', 'advanced'));

    expect($page?->level)->toBeInstanceOf(Choice::class)
        ->and($page?->level?->value)->toBe('advanced')
        ->and((string) $page?->level)->toBe('Advanced')
        ->and(lynx()->language('pt', fn (): string => (string) lynx()->pages->get('/courses/alpha/')?->level))->toBe('Avançado')
        ->and(lynx()->pages->get(choiceCourse('beta', null))?->level)->toBeNull();
});

it('reads several choices in the order of the options', function (): void {
    $formats = lynx()->pages->get(choiceCourse('alpha', 'beginner', ['hybrid', 'online']))?->formats;

    expect($formats)->toBeInstanceOf(ChoiceList::class)
        ->and($formats?->values())->toBe(['online', 'hybrid'])
        ->and($formats?->has('hybrid'))->toBeTrue()
        ->and((string) $formats)->toBe('Online, Hybrid');
});

it('refuses a value that is not an option', function (): void {
    choiceCourse('alpha', 'expert');
})->throws(ValidationException::class);

it('finds pages by their choices', function (): void {
    choiceCourse('alpha', 'beginner', ['online']);
    choiceCourse('beta', 'advanced', ['onsite', 'hybrid']);

    expect(lynx()->pages->find('level=advanced')->map(fn ($p) => $p->name()))->toBe(['beta'])
        ->and(lynx()->pages->find('formats=online|hybrid, sort=name')->map(fn ($p) => $p->name()))->toBe(['alpha', 'beta']);
});
