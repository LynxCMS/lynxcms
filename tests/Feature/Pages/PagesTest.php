<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\TranslatableValue;

beforeEach(fn (): Lynx => courseSite());

function delivery(): Page
{
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'delivery-8';
    $page->title = 'Navigating Project Delivery';
    $page->body = '<p>English body</p>';
    $page->price = 249;
    $page->setLanguageName('pt', 'navegando-entrega-8');
    $page->setLanguageValue('pt', 'title', 'Navegando pela Entrega de Projetos');
    $page->save();

    return $page;
}

it('builds translated paths from every ancestor name', function (): void {
    $page = delivery();

    expect($page->path('default'))->toBe('courses/delivery-8')
        ->and($page->path('pt'))->toBe('cursos/navegando-entrega-8')
        ->and($page->localUrl('pt'))->toBe('/pt/cursos/navegando-entrega-8')
        ->and($page->localUrl('default'))->toBe('/courses/delivery-8');
});

it('falls back field by field', function (): void {
    $page = lynx()->pages->get(delivery()->id);

    [$title, $body, $titleFallback, $bodyFallback] = lynx()->language('pt', fn (): array => [
        (string) $page->title, (string) $page->body, $page->title->isFallback(), $page->body->isFallback(),
    ]);

    expect($title)->toBe('Navegando pela Entrega de Projetos')
        ->and($body)->toBe('<p>English body</p>')
        ->and($titleFallback)->toBeFalse()
        ->and($bodyFallback)->toBeTrue()
        ->and($page->getLanguageValue('pt', 'body'))->toBeNull();
});

it('treats an explicit empty value as empty, not missing', function (): void {
    $page = delivery();
    $page->setLanguageValue('pt', 'body', '');
    $page->save();

    $page = lynx()->pages->get($page->id);

    expect(lynx()->language('pt', fn (): mixed => $page->body))->toBeNull()
        ->and($page->getLanguageValue('pt', 'body'))->toBe('')
        ->and((string) $page->body)->toBe('<p>English body</p>');
});

it('escapes plain text and trusts html fields in Blade', function (): void {
    $page = delivery();
    $page->summary = '<b>bold</b>';
    $page->save();
    $page = lynx()->pages->get($page->id);

    expect($page->summary)->toBeInstanceOf(TranslatableValue::class)
        ->and(e($page->summary))->toBe('&lt;b&gt;bold&lt;/b&gt;')
        ->and(e($page->body))->toBe('<p>English body</p>');
});

it('requires required fields in the default language only', function (): void {
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'no-title';
    $page->setLanguageValue('pt', 'title', 'Só em português');

    expect(fn () => $page->save())->toThrow(ValidationException::class);
});

it('derives the default name from the title', function (): void {
    $page = lynx()->pages->new('course', '/courses');
    $page->title = 'Agile Practice Guide!';
    $page->save();

    expect($page->name('default'))->toBe('agile-practice-guide');
});

it('rejects duplicate sibling names per language', function (): void {
    delivery();
    $other = lynx()->pages->new('course', '/courses');
    $other->name = 'delivery-8';
    $other->title = 'Other';

    expect(fn () => $other->save())->toThrow(ValidationException::class);
});

it('keeps old paths as inactive rows after a rename', function (): void {
    $page = delivery();
    $page->setLanguageName('pt', 'guia-entrega');
    $page->save();

    $rows = DB::table('lynx_page_paths')->where('page_id', $page->id)->where('language_id', lynx()->languages->get('pt')->id)
        ->orderBy('id')->get(['path', 'active'])->map(fn ($r): array => [$r->path, (bool) $r->active])->all();

    expect($rows)->toBe([['cursos/navegando-entrega-8', false], ['cursos/guia-entrega', true]]);
});

it('rebuilds descendant paths when a parent is renamed', function (): void {
    $page = delivery();
    $courses = lynx()->pages->get('/courses');
    $courses->setLanguageName('pt', 'formacao');
    $courses->save();

    expect(lynx()->pages->get($page->id)->path('pt'))->toBe('formacao/navegando-entrega-8');
});

it('moves pages and their subtree', function (): void {
    $page = delivery();
    $archive = lynx()->pages->new('courses', '/');
    $archive->name = 'archive';
    $archive->title = 'Archive';
    $archive->save();

    lynx()->pages->move($page, '/archive');

    expect(lynx()->pages->get($page->id)->path('default'))->toBe('archive/delivery-8')
        ->and(lynx()->pages->get($page->id)->path('pt'))->toBe('archive/navegando-entrega-8')
        ->and(fn () => lynx()->pages->move('/archive', $page->id))->toThrow(ValidationException::class);
});

it('switches templates keeping orphaned values and restores them', function (): void {
    lynx()->templates->add('consulting', fields: ['title']);
    $page = delivery();

    $dry = lynx()->pages->switchTemplate($page, 'consulting', dryRun: true);
    expect($dry->value['orphaned'])->toBe(['summary', 'body', 'price', 'related']);

    lynx()->pages->switchTemplate($page, 'consulting');
    expect(lynx()->pages->get($page->id)->price)->toBeNull();

    lynx()->pages->switchTemplate($page, 'course');
    expect(lynx()->pages->get($page->id)->price)->toBe(249);
});

it('purges orphaned values explicitly', function (): void {
    lynx()->templates->add('consulting', fields: ['title']);
    $page = delivery();
    lynx()->pages->switchTemplate($page, 'consulting');

    $deleted = lynx()->pages->purgeOrphans($page);

    expect($deleted)->toMatchArray(['price' => 1, 'body' => 1])
        ->and(DB::table('lynx_f_price')->count())->toBe(0);
});

it('enforces singleton templates', function (): void {
    lynx()->fields->add('footer_text', 'text', translatable: true);
    lynx()->templates->add('settings', fields: ['footer_text'], hasUrl: false, singleton: true);
    $settings = lynx()->pages->new('settings', '/');
    $settings->name = 'settings';
    $settings->footer_text = 'Footer';
    $settings->setLanguageValue('pt', 'footer_text', 'Rodapé');
    $settings->save();

    $again = lynx()->pages->new('settings', '/');
    $again->name = 'settings-2';

    expect(fn () => $again->save())->toThrow(ValidationException::class)
        ->and(lynx()->language('pt', fn (): string => (string) lynx()->settings()->footer_text))->toBe('Rodapé')
        ->and(lynx()->settings()->path('default'))->toBeNull();
});

it('loads page references as page arrays with eager loading', function (): void {
    $a = delivery();
    $b = lynx()->pages->new('course', '/courses');
    $b->title = 'Agile';
    $b->related = [$a->id];
    $b->save();

    $loaded = lynx()->pages->get($b->id);

    expect($loaded->related)->toBeInstanceOf(PageArray::class)
        ->and($loaded->related->first()->id)->toBe($a->id);
});

it('records machine origin for translated values', function (): void {
    $page = delivery();
    $page->setLanguageValue('pt', 'body', '<p>Corpo</p>');
    lynx()->pages->save($page, origin: 'machine', provider: 'test');

    $meta = DB::table('lynx_value_meta')->where('page_id', $page->id)->where('language_id', lynx()->languages->get('pt')->id)
        ->where('field_id', lynx()->fields->get('body')->id)->first();

    expect($meta->origin)->toBe('machine')->and($meta->provider)->toBe('test');
});

it('deletes pages with their subtree and values', function (): void {
    $page = delivery();
    lynx()->pages->delete('/courses');

    expect(lynx()->pages->get($page->id))->toBeNull()
        ->and(DB::table('lynx_f_title')->where('page_id', $page->id)->count())->toBe(0);
});

it('saves pages from plain arrays, as agents do', function (): void {
    $result = lynx()->run(SavePage::class, [
        'template' => 'course',
        'parent' => '/courses',
        'names' => ['default' => 'risk', 'pt' => 'risco'],
        'values' => ['title' => ['default' => 'Risk Management', 'pt' => 'Gestão de Risco'], 'price' => 99],
    ]);

    $page = lynx()->pages->get($result->value->id);

    expect($page->localUrl('pt'))->toBe('/pt/cursos/risco')
        ->and($page->getLanguageValue('pt', 'title'))->toBe('Gestão de Risco')
        ->and($page->price)->toBe(99);
});

it('refuses one value per language for a field that is not translatable, instead of dropping it', function (string $type, mixed $value): void {
    lynx()->fields->add('website', $type);
    lynx()->templates->update('course', ['add' => ['website']]);
    $page = delivery();

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['website' => ['default' => $value]]]))
        ->toThrow(ValidationException::class, 'Field [website] is not translatable: give one value, not one per language.');
})->with([
    'url' => ['url', 'https://example.com/'],
    'text' => ['text', 'Plain'],
    'integer' => ['integer', 5],
]);

it('still takes lists for shared fields that hold several values', function (): void {
    $page = delivery();
    lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['related' => [(int) lynx()->pages->get('/courses')->id]]]);

    expect(lynx()->pages->get($page->id)->related->ids())->toBe([(int) lynx()->pages->get('/courses')->id]);
});
