<?php

declare(strict_types=1);

use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Query\SelectorException;

/*
 * Selector terms on a page's state rather than its fields (ADR-0090): its status, the languages
 * it is published in, a waiting draft and scheduled changes.
 */

beforeEach(function (): void {
    courseSite();
    $this->pages = createCourses('live', 'hidden', 'unpublished', 'locked');
    $this->pages['hidden']->setStatus(PageStatus::HIDDEN)->save();
    $this->pages['unpublished']->setStatus(PageStatus::UNPUBLISHED)->save();
    $this->pages['locked']->setStatus(PageStatus::LOCKED)->save();
});

$names = fn (string $selector): array => lynx()->pages->find($selector)->map(fn ($page) => $page->name('default'));

it('finds pages by status, and brings back the ones it names', function () use ($names): void {
    expect($names('template=course, status=unpublished'))->toBe(['unpublished'])
        ->and($names('template=course, status=hidden|unpublished, sort=name'))->toBe(['hidden', 'unpublished'])
        ->and($names('template=course, status=locked'))->toBe(['locked']);
});

it('leaves out pages with a status', function () use ($names): void {
    expect($names('template=course, status!=locked, sort=name'))->toBe(['live'])
        ->and($names('template=course, status!=hidden, include=all, sort=name'))->toBe(['live', 'locked', 'unpublished']);
});

it('refuses a status it does not know', function (): void {
    lynx()->pages->find('status=archived');
})->throws(SelectorException::class);

it('finds the pages not published in a language', function () use ($names): void {
    $this->pages['live']->setLanguageName('pt', 'ao-vivo')->save();

    expect($names('template=course, language!=pt, sort=name'))->toBe(['locked'])
        ->and($names('template=course, language!=default'))->toBe([]);
});

it('keeps matching in the request\'s language with language!=', function () use ($names): void {
    $this->pages['live']->setLanguageValue('pt', 'title', 'Ao vivo')->save();

    expect($names('template=course, language!=pt, title=Ao vivo'))->toBe([])
        ->and($names('template=course, language!=pt, title=Live'))->toBe(['live']);
});

it('refuses a language it does not know', function (): void {
    lynx()->pages->find('language!=xx');
})->throws(SelectorException::class);

it('finds the pages with a waiting draft', function () use ($names): void {
    lynx()->pages->saveDraft($this->pages['live'], ['title' => 'Live draft']);

    expect($names('template=course, has_draft=1'))->toBe(['live'])
        ->and($names('template=course, has_draft=0, sort=name'))->toBe(['locked']);
});

it('finds the pages with scheduled changes', function () use ($names): void {
    lynx()->pages->schedule($this->pages['live'], 'default', unpublishAt: now()->addDay());

    expect($names('template=course, has_schedule=1'))->toBe(['live'])
        ->and(lynx()->pages->count('template=course, has_schedule!=1'))->toBe(1);
});

it('refuses yes-or-no terms with other values', function (): void {
    lynx()->pages->find('has_draft=maybe');
})->throws(SelectorException::class);

it('cannot combine state terms with fields', function (): void {
    lynx()->pages->find('status|title=hidden');
})->throws(SelectorException::class);
