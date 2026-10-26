<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\SharedValue;
use LynxCms\Core\Pages\TranslatableValue;

/*
 * ADR-0070: what templates read is safe to test with @if, ?: and ??: an empty text is null, a
 * single page reference is a page, and a PageArray joins its pages' values as strings.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('subtitle', 'text');
    lynx()->fields->add('tagline', 'text', translatable: true);
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'subtitle'], ['name' => 'tagline'], ['name' => 'partner'], ['name' => 'related']]]);
    lynx()->templates->update('course', ['add' => ['subtitle', 'tagline', 'partner', 'credits']]);
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
});

function reread(Page $page): Page
{
    return lynx()->pages->get((int) $page->id);
}

it('reads an empty shared text as null, and a filled one as a value', function (): void {
    expect(reread($this->alpha)->subtitle)->toBeNull();

    $this->alpha->subtitle = '';
    $this->alpha->save();
    expect(reread($this->alpha)->subtitle)->toBeNull()
        ->and(Blade::render('{{ $page->subtitle ?: "None" }}@if ($page->subtitle) shown @endif', ['page' => reread($this->alpha)]))->toBe('None');

    $this->alpha->subtitle = 'Hands on';
    $this->alpha->save();
    expect(reread($this->alpha)->subtitle)->toBeInstanceOf(SharedValue::class)
        ->and((string) reread($this->alpha)->subtitle)->toBe('Hands on');
});

it('reads a translatable text empty in every language as null', function (): void {
    expect(reread($this->alpha)->tagline)->toBeNull();
});

it('reads a translatable text as null when only a language outside the chain has it', function (): void {
    $this->alpha->setLanguageValue('pt', 'tagline', 'Prático');
    $this->alpha->save();

    expect(reread($this->alpha)->tagline)->toBeNull()
        ->and(Blade::render('{{ $page->tagline ?: "None" }}@if ($page->tagline) shown @endif', ['page' => reread($this->alpha)]))->toBe('None')
        ->and(reread($this->alpha)->getLanguageValue('pt', 'tagline'))->toBe('Prático')
        ->and(lynx()->language('pt', fn (): mixed => reread($this->alpha)->tagline?->value()))->toBe('Prático');
});

it('reads a translatable text found through fallback as a value', function (): void {
    $this->alpha->tagline = 'Hands on';
    $this->alpha->save();

    $isFallback = lynx()->language('pt', function (): bool {
        $tagline = reread($this->alpha)->tagline;

        return $tagline instanceof TranslatableValue && $tagline->isFallback();
    });

    expect($isFallback)->toBeTrue();
});

it('reads a translatable text that never falls back as null in a language without it', function (): void {
    lynx()->fields->add('motto', 'text', translatable: true, settings: ['fallback' => 'none']);
    lynx()->templates->update('course', ['add' => ['motto']]);
    $this->alpha->motto = 'Learn by doing';
    $this->alpha->save();

    expect(lynx()->language('pt', fn (): mixed => reread($this->alpha)->motto))->toBeNull()
        ->and((string) reread($this->alpha)->motto)->toBe('Learn by doing');
});

it('reads a single page reference as the page, or null', function (): void {
    expect(reread($this->alpha)->partner)->toBeNull();

    $this->alpha->partner = [$this->beta->id];
    $this->alpha->save();

    expect(reread($this->alpha)->partner)->toBeInstanceOf(Page::class)
        ->and(reread($this->alpha)->partner?->name())->toBe('beta')
        ->and(reread($this->alpha)->related)->toBeInstanceOf(PageArray::class);
});

it('reads item values the same way', function (): void {
    $this->alpha->credits = [
        ['values' => ['subtitle' => '', 'partner' => [$this->beta->id], 'related' => [$this->beta->id, $this->gamma->id]]],
        ['values' => ['subtitle' => 'Second', 'tagline' => ['default' => 'Hello']]],
        ['values' => ['tagline' => ['pt' => 'Olá']]],
    ];
    $this->alpha->save();
    [$first, $second, $third] = reread($this->alpha)->credits->all();

    expect($first->subtitle)->toBeNull()
        ->and($first->tagline)->toBeNull()
        ->and($first->partner?->name())->toBe('beta')
        ->and($first->related)->toBeInstanceOf(PageArray::class)
        ->and((string) $second->subtitle)->toBe('Second')
        ->and((string) $second->tagline)->toBe('Hello')
        ->and($second->partner)->toBeNull()
        ->and($third->tagline)->toBeNull()
        ->and(lynx()->language('pt', fn (): string => (string) $third->tagline))->toBe('Olá');
});

it('joins and filters the pages of a PageArray', function (): void {
    $pages = lynx()->pages->find('template=course, sort=name');

    expect($pages->implode(', ', 'title'))->toBe('Alpha, Beta, Gamma')
        ->and($pages->implode(' | ', fn (Page $page): string => $page->name()))->toBe('alpha | beta | gamma')
        ->and($pages->implode(', ', 'subtitle'))->toBe(', , ')
        ->and($pages->filter(fn (Page $page): bool => $page->name() !== 'beta'))->toBeInstanceOf(PageArray::class)
        ->and($pages->filter(fn (Page $page): bool => $page->name() !== 'beta')->implode(',', 'name'))->toBe('alpha,gamma');
});
