<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Fields\Items\ItemTable;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Schema\Draft\DraftBuilder;
use LynxCms\Core\Schema\Draft\DraftCompiler;
use LynxCms\Core\Schema\Draft\DraftOptions;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaSync;

/*
 * fields.make_translatable turns a shared field into a translatable one: each value becomes the
 * default language's value, and the other languages start empty and fall back to it. Nothing is
 * lost, so there is no way back: a translatable field never becomes shared.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('venue', 'text');
    lynx()->fields->add('note', 'text');
    lynx()->fields->add('talks', 'repeater', settings: ['fields' => [['name' => 'note']]]);
    lynx()->templates->update('course', ['add' => ['venue', 'talks']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->venue = 'Lisbon';
    $alpha->talks = [['values' => ['note' => 'Keynote']]];
    $alpha->save();
});

function translatableAlpha(): Page
{
    return lynx()->pages->get('/courses/alpha/');
}

it('keeps each value as the default language\'s, which other languages fall back to', function (): void {
    lynx()->fields->makeTranslatable('venue');

    expect(lynx()->fields->find('venue')?->isTranslatable())->toBeTrue()
        ->and((string) translatableAlpha()->venue)->toBe('Lisbon')
        ->and(lynx()->language('pt', fn (): string => (string) translatableAlpha()->venue))->toBe('Lisbon');
});

it('takes a value per language afterwards', function (): void {
    lynx()->fields->makeTranslatable('venue');
    $alpha = translatableAlpha();
    $alpha->setLanguageValue('pt', 'venue', 'Lisboa');
    $alpha->save();

    expect((string) translatableAlpha()->venue)->toBe('Lisbon')
        ->and(lynx()->language('pt', fn (): string => (string) translatableAlpha()->venue))->toBe('Lisboa');
});

it('moves a repeater item\'s value into the default language\'s row', function (): void {
    lynx()->fields->makeTranslatable('note');
    $rows = DB::table(lynx()->fields->get('talks')->table())->get()->keyBy('language_id');

    expect(json_decode((string) $rows[ItemTable::ITEM_ROW]->data, true))->not->toHaveKey('note')
        ->and(json_decode((string) $rows[lynx()->languages->default()->id]->data, true))->toBe(['note' => 'Keynote'])
        ->and((string) translatableAlpha()->talks->first()?->note)->toBe('Keynote')
        ->and(lynx()->language('pt', fn (): string => (string) translatableAlpha()->talks->first()?->note))->toBe('Keynote');
});

it('reads a revision saved before as the default language\'s value', function (): void {
    lynx()->fields->makeTranslatable('venue');

    expect(app(Revisions::class)->saveInput(translatableAlpha(), ['*' => ['venue' => 'Porto']])['venue'])->toBe(['default' => 'Porto', 'pt' => null]);
});

it('works beside a field named like the table it rebuilds from', function (): void {
    lynx()->fields->add('venue_old', 'text');

    lynx()->fields->makeTranslatable('venue');

    expect((string) translatableAlpha()->venue)->toBe('Lisbon');
});

it('refuses a field that is translatable, or whose type can\'t be', function (string $field): void {
    lynx()->fields->makeTranslatable($field);
})->with(['summary', 'price'])->throws(ValidationException::class);

it('changes nothing on a dry run', function (): void {
    expect(lynx()->fields->makeTranslatable('venue', dryRun: true))->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->fields->find('venue')?->isTranslatable())->toBeFalse()
        ->and((string) translatableAlpha()->venue)->toBe('Lisbon');
});

it('is what schema sync does when a file makes a shared field translatable', function (): void {
    $item = app(SchemaExporter::class)->fieldData(lynx()->fields->get('venue'));
    $result = app(SchemaSync::class)->applyItems([['translatable' => true] + $item], []);

    expect($result->errors)->toBe([])
        ->and(lynx()->fields->find('venue')?->isTranslatable())->toBeTrue()
        ->and((string) translatableAlpha()->venue)->toBe('Lisbon');
});

it('is what a draft does when it says translatable for a shared field', function (): void {
    $draft = app(DraftCompiler::class)->compile(['fields' => ['venue' => 'text translatable']]);

    expect(app(DraftBuilder::class)->plan($draft, new DraftOptions(writeFiles: false))->lines())->toBe(['update field venue']);
});

it('leaves schema sync refusing to make a translatable field shared', function (): void {
    $item = app(SchemaExporter::class)->fieldData(lynx()->fields->get('summary'));

    expect(app(SchemaSync::class)->applyItems([['translatable' => false] + $item], [])->errors)->not->toBe([]);
});
