<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Ai\Translation\LaravelAiTranslationProvider;
use LynxCms\Ai\Translation\TranslatorAgent;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

beforeEach(function (): void {
    courseSite();
    $this->provider = new FakeTranslationProvider;
    app()->instance(TranslationProvider::class, $this->provider);

    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'risk';
    $page->title = 'Risk Management';
    $page->summary = 'Identify and respond to risk.';
    $page->body = '<p>Long English body.</p>';
    $page->save();
    $this->page = $page;
});

it('translates missing values, marks them as machine and names the page', function (): void {
    lynx()->pages->translate('/courses/risk/', 'pt');
    $page = lynx()->pages->get($this->page->id);

    expect($page->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Risk Management')
        ->and($page->getLanguageValue('pt', 'body'))->toBe('<p>[pt-PT] Long English body.</p>')
        ->and($page->isActiveIn('pt'))->toBeTrue()
        ->and($page->path('pt'))->toBe('cursos/pt-pt-risk-management')
        ->and(DB::table('lynx_value_meta')->where('page_id', $page->id)->where('origin', 'machine')->count())->toBe(3);
});

it('never overwrites human translations', function (): void {
    $this->page->setLanguageValue('pt', 'title', 'Gestão de Risco');
    $this->page->setLanguageName('pt', 'gestao-de-risco');
    $this->page->save();

    lynx()->pages->translate('/courses/risk/', 'pt', overwrite: true);

    expect(lynx()->pages->get($this->page->id)->getLanguageValue('pt', 'title'))->toBe('Gestão de Risco')
        ->and($this->provider->calls[0])->not->toHaveKey('title');
});

it('marks a hand edit as human again', function (): void {
    lynx()->pages->translate('/courses/risk/', 'pt');
    $page = lynx()->pages->get($this->page->id);
    $page->setLanguageValue('pt', 'summary', 'Editado à mão.');
    $page->save();

    $origin = DB::table('lynx_value_meta')->where('page_id', $page->id)
        ->where('field_id', lynx()->fields->get('summary')->id)->where('language_id', lynx()->languages->get('pt')->id)->value('origin');

    expect($origin)->toBe('human');
});

it('translates through the Laravel AI SDK with structured output', function (): void {
    TranslatorAgent::fake([['translations' => ['title' => 'Gestão de Risco', 'body' => '<p>Corpo.</p>']]]);

    $result = (new LaravelAiTranslationProvider)->translate(['title' => 'Risk', 'body' => '<p>Body.</p>'], 'en', 'pt-PT', ['body' => 'html']);

    expect($result)->toBe(['title' => 'Gestão de Risco', 'body' => '<p>Corpo.</p>']);
    TranslatorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Translate from en to pt-PT'));
});
