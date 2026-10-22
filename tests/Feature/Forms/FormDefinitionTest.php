<?php

declare(strict_types=1);

use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormValidator;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
});

/** @param array<string, mixed> $data */
function quoteErrors(array $data, ?array $only = null, string $language = 'default'): array
{
    return lynx()->language($language, function () use ($data, $only): array {
        $validator = app(FormValidator::class)->make(lynx()->forms()->get('quote'), $data, $only);

        return $validator->errors()->toArray();
    });
}

it('registers code forms by key', function (): void {
    expect(lynx()->forms()->get('quote'))->toBeInstanceOf(QuoteForm::class)
        ->and(lynx()->forms()->get('nope'))->toBeNull()
        ->and(array_keys(lynx()->forms()->all()))->toBe(['quote']);
});

it('checks only the fields a value shows', function (): void {
    expect(quoteErrors(['type' => 'film']))->not->toHaveKey('data.cameras')
        ->and(quoteErrors(['type' => 'live']))->toHaveKey('data.cameras');
});

it('checks each row of a group', function (): void {
    $errors = quoteErrors(['type' => 'film', 'deliverables' => [['format' => 'Teaser'], ['minutes' => 3]]]);

    expect($errors)->toHaveKey('data.deliverables.1.format')->not->toHaveKey('data.deliverables.0.format');
});

it('checks one step at a time', function (): void {
    expect(quoteErrors([], ['type', 'cameras', 'deliverables', 'brief']))->toHaveKey('data.type')->not->toHaveKey('data.name');
});

it('names fields and words messages in the visitor\'s language', function (): void {
    expect(quoteErrors([], language: 'pt')['data.consent'][0])->toBe('Aceite para podermos responder.')
        ->and(quoteErrors([])['data.consent'][0])->toBe('Please agree so we can reply.')
        ->and(quoteErrors([], language: 'pt')['data.name'][0])->toContain('Nome');
});

it('refuses options the field does not offer', function (): void {
    expect(quoteErrors(['type' => 'opera']))->toHaveKey('data.type');
});

it('keeps a snapshot of the fields with their labels', function (): void {
    $snapshot = lynx()->forms()->get('quote')->snapshot();

    expect($snapshot[0])->toMatchArray(['name' => 'type', 'type' => 'select', 'label' => ['default' => 'Project type', 'pt' => 'Tipo de projeto']])
        ->and($snapshot[2]['fields'][0]['name'])->toBe('format');
});

it('shows a field when a number compares with a value', function (string $operator, int $value, array $shownFor, bool $shownForText): void {
    $field = FormField::text('lights')->showWhen('cameras', $operator, $value);

    expect(array_values(array_filter([1, 3, 5], fn (int $cameras): bool => $field->isShown(['cameras' => (string) $cameras]))))->toBe($shownFor)
        ->and($field->isShown([]))->toBeFalse()
        ->and($field->isShown(['cameras' => 'many']))->toBe($shownForText);
})->with([
    // <, <=, > and >= need a number; = and != compare values, so "many" is not 3.
    'less than' => ['<', 3, [1], false],
    'up to' => ['<=', 3, [1, 3], false],
    'equal' => ['=', 3, [3], false],
    'not equal' => ['!=', 3, [1, 5], true],
    'at least' => ['>=', 3, [3, 5], false],
    'more than' => ['>', 3, [5], false],
]);

it('shows a field when a tick box is ticked, or when it is not', function (): void {
    $ticked = FormField::text('reason')->showWhen('newsletter', '=', true);
    $unticked = FormField::text('reason')->showWhen('newsletter', '=', false);

    expect($ticked->isShown(['newsletter' => true]))->toBeTrue()
        ->and($ticked->isShown(['newsletter' => false]))->toBeFalse()
        ->and($ticked->isShown([]))->toBeFalse()
        ->and($unticked->isShown([]))->toBeTrue()
        ->and($unticked->isShown(['newsletter' => '0']))->toBeTrue()
        ->and($unticked->isShown(['newsletter' => true]))->toBeFalse();
});

it('refuses comparison operators it does not know', function (): void {
    FormField::text('a')->showWhen('b', '~', 1);
})->throws(InvalidArgumentException::class);
