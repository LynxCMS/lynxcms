<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Pages\SavePage;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('role', 'text', translatable: true, label: ['default' => 'Role', 'pt' => 'Função']);
    lynx()->fields->add('quote', 'text', translatable: true);
    lynx()->fields->add('credits', 'repeater', settings: [
        'fields' => [['name' => 'partner', 'required' => true, 'width' => 50], ['name' => 'role', 'width' => 50]],
        'item_label' => '{partner} · {role}',
        'add_label' => ['default' => 'Add credit', 'pt' => 'Adicionar crédito'],
    ]);
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
        ['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'summary']]],
        ['slug' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'quote', 'required' => true]]],
    ]]);
    lynx()->templates->update('course', ['add' => ['credits', 'blocks']]);
    ['alpha' => $alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    $alpha->credits = [['values' => ['partner' => [$this->beta->id], 'role' => ['default' => 'Direction']]]];
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

function itemEditor(): Testable
{
    return Livewire::test(PageEditor::class, ['pageId' => test()->alphaId]);
}

/** @return list<array<string, mixed>> */
function storedCredits(): array
{
    return lynx()->pages->get('/courses/alpha/')?->getUnformatted('credits') ?? [];
}

it('shows items with headers from the item label, and an add button', function (): void {
    itemEditor()->assertSee('Beta · Direction')->assertSee('Add credit')->assertSee('No items yet.');
});

it('adds an item and saves it', function (): void {
    itemEditor()
        ->call('addItem', 'credits')
        ->set('values.credits.default.1.values.partner.default', [test()->gamma->id])
        ->set('values.credits.default.1.values.role.default', 'Editing')
        ->set('values.credits.default.1.values.role.pt', 'Montagem')
        ->call('save')->assertHasNoErrors()->assertDispatched('lynx-notify', message: 'Saved.');

    expect(storedCredits())->toHaveCount(2)
        ->and(storedCredits()[1]['values'])->toBe(['partner' => [test()->gamma->id], 'role' => ['default' => 'Editing', 'pt' => 'Montagem']]);
});

it('switches an item off, sorts and removes items', function (): void {
    $editor = itemEditor()->call('addItem', 'credits')->set('values.credits.default.1.values.partner.default', [test()->gamma->id]);
    $second = $editor->get('values.credits.default.1.key');

    $editor->call('toggleItem', 'credits', 0)->call('sortItem', "credits:{$second}", 0)->call('save')->assertHasNoErrors();

    expect(array_column(storedCredits(), 'enabled'))->toBe([true, false])
        ->and(storedCredits()[0]['key'])->toBe($second);

    itemEditor()->call('removeItem', 'credits', 0)->call('save');
    expect(storedCredits())->toHaveCount(1);
});

it('shows errors by item and subfield', function (): void {
    itemEditor()->call('addItem', 'credits')->call('save')->assertHasErrors('values.credits.1.partner');
});

it('adds matrix blocks of the types the field offers', function (): void {
    itemEditor()->assertSee('Text')->assertSee('Quote')
        ->call('addItem', 'blocks', 'quote')
        ->call('addItem', 'blocks', 'nope')
        ->set('values.blocks.default.0.values.quote.default', 'Less is more')
        ->call('save')->assertHasNoErrors();

    expect(lynx()->pages->get('/courses/alpha/')?->getUnformatted('blocks'))->toHaveCount(1)
        ->and(lynx()->pages->get('/courses/alpha/')?->getUnformatted('blocks')[0]['type'])->toBe('quote');
});

it('offers only the types a template allows', function (): void {
    lynx()->templates->update('course', ['fields' => ['title', 'summary', 'body', 'price', 'related', 'credits', ['name' => 'blocks', 'allowed_types' => ['quote']]]]);

    itemEditor()->assertSeeHtml("addItem('blocks', 'quote')")->assertDontSeeHtml("addItem('blocks', 'text')");
});

it('reviews item changes as a summary', function (): void {
    itemEditor()->call('addItem', 'credits')->set('values.credits.default.1.values.partner.default', [test()->gamma->id])
        ->call('review')->assertSet('isReviewing', true)->assertSee('2 items');
});

it('removes a translation cleared in one language', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['credits' => [['key' => storedCredits()[0]['key'], 'values' => ['role' => ['pt' => 'Realização']]]]]]);
    expect(storedCredits()[0]['values']['role'])->toBe(['default' => 'Direction', 'pt' => 'Realização']);

    itemEditor()->set('values.credits.default.0.values.role.pt', '')->call('save')->assertHasNoErrors();

    expect(storedCredits()[0]['values']['role'])->toBe(['default' => 'Direction']);
});

it('removes a subfield cleared in every language', function (): void {
    itemEditor()->set('values.credits.default.0.values.role.default', '')->call('save')->assertHasNoErrors();

    expect(storedCredits()[0]['values'])->not->toHaveKey('role');
});

it('shows page references in items as titles while another user edits the page', function (): void {
    itemEditor();
    test()->actingAs(new GenericUser(['id' => 2]));

    itemEditor()->assertSet('editedBy', fn (?string $holder): bool => $holder !== null)->assertSee('Beta');
});
