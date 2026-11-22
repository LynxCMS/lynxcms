<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Languages\EditorLanguage;

/*
 * What a refused save says (ADR-0141): Lynx's own messages in the editor's language, and Laravel's
 * rule messages naming the field by its label instead of "value".
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('caption', 'text');
    lynx()->fields->add('weight', 'integer');
    lynx()->fields->add('credits', 'repeater', settings: ['max' => 1, 'fields' => [['name' => 'caption', 'required' => true], ['name' => 'weight']]]);
    lynx()->fields->update('title', ['label' => ['default' => 'Title', 'pt' => 'Título']]);
    lynx()->fields->update('price', ['label' => ['default' => 'Price', 'pt' => 'Preço']]);
    lynx()->fields->update('weight', ['label' => 'Weight']);
    lynx()->templates->update('course', ['add' => [['name' => 'credits']]]);
});

/**
 * @param  array<string, mixed>  $values
 * @return array<string, list<string>>
 */
function refusals(array $values, string $locale = 'en'): array
{
    app()->setLocale($locale);
    app(EditorLanguage::class)->use($locale);

    try {
        lynx()->run(SavePage::class, ['template' => 'course', 'parent' => '/courses/', 'names' => ['default' => 'x'], 'values' => $values]);
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('names a missing field by its label, in the editor\'s language', function (string $locale, string $label): void {
    $message = refusals([], $locale)['values.title'][0];

    expect($message)->toBe(__('lynx::validation.values.required_default', ['field' => $label], $locale))
        ->and($message)->toContain($label);
})->with([['en', 'Title'], ['pt', 'Título'], ['es', 'Title']]);

it('says something different in each language Lynx ships', function (): void {
    $messages = array_map(fn (string $locale): string => refusals([], $locale)['values.title'][0], ['en', 'pt', 'es']);

    expect(array_unique($messages))->toHaveCount(3);
});

it('names the field in the messages of Laravel\'s rules', function (string $locale, string $label): void {
    expect(refusals(['title' => 'X', 'price' => 'abc'], $locale)['values.price'][0])
        ->toBe(__('validation.integer', ['attribute' => $label], $locale));
})->with([['en', 'Price'], ['pt', 'Preço']]);

it('names the field of a repeater\'s item', function (): void {
    $errors = refusals(['title' => 'X', 'credits' => [['values' => ['caption' => 'A', 'weight' => 'abc']]]]);

    expect($errors['values.credits.0.weight'][0])->toBe(__('validation.integer', ['attribute' => 'Weight']));
});

it('counts in the plural of the language', function (): void {
    $items = [['values' => ['caption' => 'A']], ['values' => ['caption' => 'B']]];

    expect(refusals(['title' => 'X', 'credits' => $items], 'pt')['values.credits'][0])
        ->toBe(trans_choice('lynx::validation.items.max', 1, [], 'pt'));
});

it('reads an app\'s own rule messages', function (): void {
    $lang = testingPath('rule-messages');
    File::ensureDirectoryExists($lang.'/pt');
    File::put($lang.'/pt/validation.php', "<?php return ['integer' => 'O campo :attribute deve ser um número inteiro.'];");
    app('translation.loader')->addPath($lang);
    $message = refusals(['title' => 'X', 'price' => 'abc'], 'pt')['values.price'][0];
    File::deleteDirectory($lang);

    expect($message)->toBe('O campo Preço deve ser um número inteiro.');
});
