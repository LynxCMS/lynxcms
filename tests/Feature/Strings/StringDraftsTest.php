<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\DiscardDraft;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Revisions;

beforeEach(function (): void {
    courseSite();
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('landing', fields: ['title']);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing';
    $page->title = 'Landing';
    $page->save();
    $this->landingId = (int) lynx()->pages->get('/landing/')?->id;
});

/** The landing page rendered in Portuguese, live or from its draft. */
function landingIn(bool $draft = false): string
{
    $page = $draft ? lynx()->pages->draft(test()->landingId) : lynx()->pages->get('/landing/');

    return lynx()->language('pt', fn (): string => view('templates.landing', ['page' => $page])->render());
}

/** @param array<string, string|null> $values */
function editStrings(array $values, bool $draft = false): mixed
{
    return lynx()->run(SaveStrings::class, ['page' => test()->landingId, 'language' => 'pt', 'values' => $values, 'draft' => $draft])->value;
}

it('saves strings to the draft and leaves the live page alone until it is published', function (): void {
    editStrings(['hero.title' => 'Rascunho'], draft: true);

    expect(landingIn())->toContain('<h1>Produções audiovisuais</h1>')
        ->and(landingIn(draft: true))->toContain('<h1>Rascunho</h1>');

    lynx()->run(PublishDraft::class, ['page' => test()->landingId]);

    expect(landingIn())->toContain('<h1>Rascunho</h1>')
        ->and(DB::table('lynx_strings')->count())->toBe(1);
});

it('starts a draft from the page\'s edited strings', function (): void {
    editStrings(['hero.title' => 'Ao vivo']);
    editStrings(['cta.label' => 'Fale connosco'], draft: true);

    expect(landingIn(draft: true))->toContain('<h1>Ao vivo</h1>')->toContain('Fale connosco');

    lynx()->run(PublishDraft::class, ['page' => test()->landingId]);

    expect(landingIn())->toContain('<h1>Ao vivo</h1>')->toContain('Fale connosco');
});

it('publishes a string removed in the draft as removed', function (): void {
    editStrings(['hero.title' => 'Ao vivo']);
    editStrings(['hero.title' => null], draft: true);
    lynx()->run(PublishDraft::class, ['page' => test()->landingId]);

    expect(landingIn())->toContain('<h1>Produções audiovisuais</h1>');
});

it('drops the draft\'s strings with the draft', function (): void {
    editStrings(['hero.title' => 'Rascunho'], draft: true);
    lynx()->run(DiscardDraft::class, ['page' => test()->landingId]);

    expect(DB::table('lynx_strings')->count())->toBe(0)->and(landingIn())->toContain('<h1>Produções audiovisuais</h1>');
});

it('refuses to draft site strings, which every page shares', function (): void {
    editStrings(['footer.note' => 'Esfera Cúbica'], draft: true);
})->throws(ValidationException::class, 'Site texts');

it('records each strings edit as a revision and restores it', function (): void {
    editStrings(['hero.title' => 'Um']);
    editStrings(['hero.title' => 'Dois']);
    $revisions = app(Revisions::class)->of(test()->landingId);

    lynx()->run(RestoreRevision::class, ['page' => test()->landingId, 'revision' => $revisions[1]['id']]);

    expect(landingIn())->toContain('<h1>Um</h1>');
});

it('takes strings in pages.save, by language', function (): void {
    $page = lynx()->run(SavePage::class, ['id' => test()->landingId, 'strings' => ['pt' => ['hero.title' => 'Direto']]])->valueAs(Page::class);

    expect($page->id)->toBe(test()->landingId)->and(landingIn())->toContain('<h1>Direto</h1>');
});

it('refuses unknown keys, site keys and languages in pages.save', function (array $strings, string $message): void {
    expect(fn (): mixed => lynx()->run(SavePage::class, ['id' => test()->landingId, 'strings' => $strings]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'unknown key' => [['pt' => ['nope' => 'x']], 'nope'],
    'site key' => [['pt' => ['footer.note' => 'x']], 'footer.note'],
    'unknown language' => [['xx' => ['hero.title' => 'x']], 'xx'],
]);

it('writes no strings on a dry run', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->landingId, 'strings' => ['pt' => ['hero.title' => 'x']]], dryRun: true);

    expect(DB::table('lynx_strings')->count())->toBe(0);
});

it('shows the draft\'s strings in a draft preview', function (): void {
    editStrings(['hero.title' => 'Rascunho'], draft: true);
    $preview = lynx()->pages->get('/landing/')?->withValuesOf(lynx()->pages->draft(test()->landingId));

    expect(lynx()->language('pt', fn (): string => view('templates.landing', ['page' => $preview])->render()))->toContain('<h1>Rascunho</h1>');
});
