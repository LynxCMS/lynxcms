<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Pages\Items\Item;
use LynxCms\Core\Pages\Items\ItemList;
use LynxCms\Core\Pages\Page;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('role', 'text', translatable: true);
    lynx()->fields->add('credits', 'repeater', label: ['default' => 'Credits', 'pt' => 'Créditos'], settings: [
        'fields' => [['name' => 'partner', 'required' => true], ['name' => 'role', 'label' => ['pt' => 'Função']]],
        'max' => 3,
        'item_label' => '{partner} · {role}',
    ]);
    lynx()->templates->update('course', ['add' => ['credits']]);
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
});

/** Credits for alpha: beta directed (in both languages), gamma edited (switched off). */
function credit(): Page
{
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->credits = [
        ['values' => ['partner' => [test()->beta->id], 'role' => ['default' => 'Direction', 'pt' => 'Realização']]],
        ['enabled' => false, 'values' => ['partner' => [test()->gamma->id], 'role' => ['default' => 'Editing']]],
    ];
    $alpha->save();

    return lynx()->pages->get('/courses/alpha/');
}

it('saves items and reads the enabled ones in order', function (): void {
    $credits = credit()->credits;

    expect($credits)->toBeInstanceOf(ItemList::class)
        ->and($credits)->toHaveCount(1)
        ->and($credits->all())->toHaveCount(2)
        ->and($credits->first())->toBeInstanceOf(Item::class)
        ->and($credits->first()?->partner?->name())->toBe('beta')
        ->and((string) $credits->first()?->role)->toBe('Direction')
        ->and(lynx()->language('pt', fn (): string => (string) credit()->credits->first()?->role))->toBe('Realização');
});

it('falls back per subfield along the language chain', function (): void {
    $editing = credit()->credits->all()[1];

    lynx()->language('pt', function () use ($editing): void {
        expect((string) $editing->role)->toBe('Editing')->and($editing->role->isFallback())->toBeTrue();
    });
});

it('stores one row per item and one per language with translations', function (): void {
    credit();
    $rows = DB::table('lynx_f_credits')->where('page_id', test()->alpha->id)->orderBy('language_id')->get();

    expect($rows)->toHaveCount(5)
        ->and($rows->where('language_id', 0)->pluck('sort')->sort()->values()->all())->toBe([0, 1])
        ->and($rows->where('language_id', 0)->pluck('item')->unique())->toHaveCount(2)
        ->and(json_decode((string) $rows->where('language_id', 0)->firstWhere('sort', 0)->data, true))->toBe(['partner' => [test()->beta->id]]);
});

it('returns the stored items unformatted, with keys', function (): void {
    $items = credit()->getUnformatted('credits');

    expect($items)->toHaveCount(2)
        ->and($items[0])->toMatchArray(['enabled' => true, 'depth' => 0, 'values' => ['partner' => [test()->beta->id], 'role' => ['default' => 'Direction', 'pt' => 'Realização']]])
        ->and($items[0]['key'])->toMatch('/^[0-9A-Z]{26}$/');
});

it('keeps item keys and other languages when one language is saved', function (): void {
    $items = credit()->getUnformatted('credits');
    $items[0]['values']['role'] = 'Direção';
    lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'language' => 'pt', 'values' => ['credits' => $items]]);

    $saved = lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits');
    expect($saved[0]['key'])->toBe($items[0]['key'])
        ->and($saved[0]['values']['role'])->toBe(['default' => 'Direction', 'pt' => 'Direção'])
        ->and($saved[1]['values']['role'])->toBe(['default' => 'Editing']);
});

it('reports errors by item and subfield', function (): void {
    try {
        lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'values' => ['credits' => [['values' => ['role' => 'Sound']]]]]);
        $this->fail('No validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('values.credits.0.partner');
    }
});

it('refuses translations in languages that do not exist', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'values' => ['credits' => [['values' => ['partner' => [1], 'role' => ['xx' => 'Nope']]]]]]);
})->throws(ValidationException::class);

it('refuses more items than allowed', function (): void {
    $item = ['values' => ['partner' => [test()->beta->id]]];
    lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'values' => ['credits' => [$item, $item, $item, $item]]]);
})->throws(ValidationException::class, 'at most 3');

it('refuses subfields that do not exist or store items themselves', function (): void {
    expect(fn () => lynx()->fields->add('bad', 'repeater', settings: ['fields' => [['name' => 'nope']]]))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->add('nested', 'repeater', settings: ['fields' => [['name' => 'credits']]]))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->add('shared', 'repeater', translatable: true, settings: ['fields' => [['name' => 'role']]]))->toThrow(ValidationException::class);
});

it('writes nothing on a dry run', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'values' => ['credits' => [['values' => ['partner' => [test()->beta->id]]]]]], dryRun: true);

    expect(DB::table('lynx_f_credits')->count())->toBe(0);
});

it('rolls back with the save', function (): void {
    lynx()->hook(SavePage::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): Page => credit())->toThrow(ActionHalted::class)
        ->and(DB::table('lynx_f_credits')->count())->toBe(0);
});

it('carries items through drafts', function (): void {
    credit();
    $items = lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits');
    $items[0]['values']['role']['pt'] = 'Direção de arte';
    lynx()->pages->saveDraft('/courses/alpha/', ['credits' => $items]);

    expect(lynx()->language('pt', fn (): string => (string) lynx()->pages->get('/courses/alpha/')?->credits->first()?->role))->toBe('Realização');

    lynx()->pages->publishDraft('/courses/alpha/');

    expect(lynx()->language('pt', fn (): string => (string) lynx()->pages->get('/courses/alpha/')?->credits->first()?->role))->toBe('Direção de arte')
        ->and(lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits'))->toHaveCount(2);
});

it('restores items from a revision', function (): void {
    credit();
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->credits = [];
    $alpha->save();
    $revision = lynx()->pages->revisions($alpha)[1]['id'];

    lynx()->run(RestoreRevision::class, ['page' => $alpha->id, 'revision' => $revision]);

    expect(lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits'))->toHaveCount(2);
});

it('lists the pages credited as backlinks and eager loads them', function (): void {
    credit();

    expect(lynx()->pages->backlinks('/courses/beta/')->map(fn (Page $page): string => $page->name()))->toBe(['alpha'])
        ->and(lynx()->pages->find('id='.test()->alpha->id, with: ['credits'])->first()?->credits->first()?->partner?->name())->toBe('beta');
});

it('moves items between sites with page keys', function (): void {
    credit();
    $dir = sys_get_temp_dir().'/lynx-repeater-'.uniqid();
    app(ContentExporter::class)->export($dir);
    $line = collect(explode("\n", File::get($dir.'/pages.jsonl')))->filter()->map(fn (string $l): array => json_decode($l, true))->firstWhere('key', '/courses/alpha/');

    expect($line['values']['*']['credits'][0]['values']['partner'])->toBe(['/courses/beta/']);

    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->credits = [];
    $alpha->save();
    lynx()->run(ImportContent::class, ['path' => $dir]);
    File::deleteDirectory($dir);

    expect(lynx()->pages->get('/courses/alpha/')?->credits->first()?->partner?->name())->toBe('beta');
});

it('is a field of its own type that cannot be translatable', function (): void {
    expect(lynx()->fields->get('credits')->isTranslatable())->toBeFalse()
        ->and(lynx()->language('pt', fn () => lynx()->fields->get('credits')->label))->toBe('Créditos');
});

it('translates the missing translations of item subfields', function (): void {
    app()->instance(TranslationProvider::class, $provider = new FakeTranslationProvider);
    credit();

    lynx()->pages->translate('/courses/alpha/', 'pt');
    $items = lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits');

    expect($items[0]['values']['role'])->toBe(['default' => 'Direction', 'pt' => 'Realização'])
        ->and($items[1]['values']['role']['pt'])->toBe('[pt-PT] Editing')
        ->and(array_keys($provider->calls[0]))->toContain('credits.'.$items[1]['key'].'.role');
});

it('refuses to delete a field a repeater uses as a subfield', function (): void {
    expect(fn (): mixed => lynx()->fields->delete('partner'))->toThrow(ValidationException::class, 'credits')
        ->and(lynx()->fields->find('partner'))->not->toBeNull();
});
