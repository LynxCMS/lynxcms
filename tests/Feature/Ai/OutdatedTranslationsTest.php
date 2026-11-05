<?php

declare(strict_types=1);

use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Pages\Page;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

/*
 * A machine translation remembers the text it was made from (ADR-0016) and the language of that
 * text (ADR-0102). When that text changes, the translation is outdated, until the text changes
 * back or the field is translated again. A person's translation is never outdated.
 */

beforeEach(function (): void {
    courseSite();
    app()->instance(TranslationProvider::class, new FakeTranslationProvider);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->summary = 'A first course.';
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    lynx()->pages->translate($this->alphaId, 'pt');
});

function outdatedAlpha(): Page
{
    return lynx()->pages->get(test()->alphaId) ?? throw new RuntimeException('No alpha.');
}

function editOutdatedAlpha(string $field, string $text): void
{
    $page = outdatedAlpha();
    $page->{$field} = $text;
    $page->save();
}

/** @return array<string, bool> whether each field's Portuguese translation is outdated */
function outdatedInPortuguese(?Page $page = null): array
{
    return array_map(fn (array $state): bool => $state['outdated'], lynx()->pages->translationStates($page ?? outdatedAlpha(), 'pt'));
}

it('says how each field was translated', function (): void {
    expect(lynx()->pages->translationStates(outdatedAlpha(), 'pt'))->toBe([
        'title' => ['origin' => 'machine', 'provider' => 'fake', 'outdated' => false],
        'summary' => ['origin' => 'machine', 'provider' => 'fake', 'outdated' => false],
    ]);
});

it('marks a translation outdated when the text it was made from changes', function (): void {
    editOutdatedAlpha('title', 'Alpha, revised');

    expect(outdatedInPortuguese())->toBe(['title' => true, 'summary' => false]);
});

it('clears it when the text changes back', function (): void {
    editOutdatedAlpha('title', 'Alpha, revised');
    editOutdatedAlpha('title', 'Alpha');

    expect(outdatedInPortuguese()['title'])->toBeFalse();
});

it('clears it when the field is translated again', function (): void {
    editOutdatedAlpha('title', 'Alpha, revised');

    lynx()->pages->translate($this->alphaId, 'pt', overwrite: true);

    expect(outdatedInPortuguese()['title'])->toBeFalse()
        ->and(outdatedAlpha()->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Alpha, revised');
});

it('never marks a person\'s translation outdated', function (): void {
    $page = outdatedAlpha();
    $page->setLanguageValue('pt', 'title', 'Alfa');
    $page->save();

    editOutdatedAlpha('title', 'Alpha, revised');

    expect(lynx()->pages->translationStates(outdatedAlpha(), 'pt')['title'])->toBe(['origin' => 'human', 'provider' => null, 'outdated' => false]);
});

it('marks it in the draft when the draft\'s text changes, and publishing keeps it', function (): void {
    lynx()->pages->saveDraft($this->alphaId, ['title' => ['default' => 'Alpha, drafted']]);

    expect(outdatedInPortuguese(lynx()->pages->draft($this->alphaId))['title'])->toBeTrue()
        ->and(outdatedInPortuguese()['title'])->toBeFalse();

    lynx()->run(PublishDraft::class, ['page' => $this->alphaId]);

    expect(outdatedInPortuguese()['title'])->toBeTrue();
});
