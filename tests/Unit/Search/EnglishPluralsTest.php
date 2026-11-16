<?php

declare(strict_types=1);

use LynxCms\Core\Search\Plurals\EnglishPlurals;

it('turns a plural into its singular', function (string $plural, string $singular): void {
    expect((new EnglishPlurals)->reduce($plural))->toBe($singular);
})->with([
    ['cities', 'city'],
    ['classes', 'class'],
    ['boxes', 'box'],
    ['churches', 'church'],
    ['wishes', 'wish'],
    ['quizzes', 'quiz'],
    ['books', 'book'],
    ['cases', 'case'],
    ['sizes', 'size'],
    ['movies', 'movie'],
    ['caches', 'cache'],
]);

it('leaves singulars and words that only look plural as they are', function (string $word): void {
    expect((new EnglishPlurals)->reduce($word))->toBe($word);
})->with(['book', 'class', 'status', 'analysis', 'famous', 'series', 'news', 'does', 'bus']);
