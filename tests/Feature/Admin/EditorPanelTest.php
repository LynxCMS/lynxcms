<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\EditorPanel;
use LynxCms\Admin\Editor\FieldViews;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

/*
 * The panel beside the page editor's fields: whether the page is published or has a draft, how
 * much of it each language has its own text for, and its recent activity. Templates that lay
 * out their own sidebar keep it instead (ADR-0063).
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function panelCourse(): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'budgets';
    $page->title = 'Budgets for small projects';
    $page->summary = 'Plan and track a budget.';
    $page->body = '<p>Most small projects never get a real budget.</p>';
    $page->setLanguageName('pt', 'orcamentos');
    $page->setLanguageValue('pt', 'title', 'Orçamentos para pequenos projetos');
    $page->save();

    return $page;
}

it('shows the panel beside the fields of a template without its own sidebar', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => panelCourse()->id])
        ->assertSeeHtml('data-editor-panel');
});

it('leaves the panel out where the template lays out its own sidebar', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'column' => 'sidebar'], 'related']]);

    Livewire::test(PageEditor::class, ['pageId' => panelCourse()->id])
        ->assertDontSeeHtml('data-editor-panel');
});

it('counts the translatable fields each language has its own text for', function (): void {
    $page = panelCourse();
    $panel = (new EditorPanel)->for($page, (new FieldViews)->for($page), hasDraft: false);

    expect(array_map(fn (array $language): array => [$language['code'], $language['own'], $language['total']], $panel['languages']))
        ->toBe([['default', 3, 3], ['pt', 1, 3]]);
});

it('counts no languages on a site with one', function (): void {
    lynx()->languages->delete('pt');
    $page = createCourses('alpha')['alpha'];

    expect((new EditorPanel)->for($page, (new FieldViews)->for($page), hasDraft: false)['languages'])->toBe([]);
});

it('lists the page\'s latest activity, newest first, up to a few entries', function (): void {
    $page = panelCourse();

    foreach (['One', 'Two', 'Three', 'Four'] as $title) {
        $page->title = $title;
        $page->save();
    }

    $activity = (new EditorPanel)->for($page, (new FieldViews)->for($page), hasDraft: false)['activity'];

    expect($activity)->toHaveCount(EditorPanel::RECENT_ACTIVITY)
        ->and($activity[0])->toHaveKeys(['when', 'at', 'who', 'summary', 'more']);
});

it('puts every label above its input', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => panelCourse()->id])
        ->assertDontSeeHtml('@xl:grid-cols-[12rem_minmax(0,1fr)]');
});
