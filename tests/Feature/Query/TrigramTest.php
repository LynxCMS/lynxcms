<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\PageFinder;

/*
 * ADR-0061: trigram indexes on PostgreSQL serve the contains operators; with lynx.trigram on,
 * translatable fields match them language by language so the index applies.
 */

beforeEach(function (): void {
    courseSite();

    foreach ([['risk', 'Risk management', 'Gestão de riscos'], ['agile', 'Agile teams', null]] as [$name, $title, $ptTitle]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = $title;
        if ($ptTitle !== null) {
            $page->setLanguageValue('pt', 'title', $ptTitle);
        }
        $page->save();
    }
});

$names = fn (string $selector): array => lynx()->language('pt', fn (): array => lynx()->pages->find($selector.', sort=name')->map(fn (Page $page): string => (string) $page->name));
$trigramIndex = fn (string $field): bool => collect(Schema::getIndexes((string) lynx()->fields->find($field)?->table()))->contains(fn (array $index): bool => str_ends_with($index['name'], '_trgm'));

it('matches contains clauses language by language when trigram is on', function () use ($names): void {
    $before = [$names('template=course, title%=risc'), $names('template=course, title~="teams agile"'), $names('template=course, title^=gest')];
    config(['lynx.trigram' => true]);
    $sql = lynx()->language('pt', fn (): string => app(PageFinder::class)->compile('template=course, title%=risc')->toSql());

    expect($sql)->not->toContain('COALESCE')
        ->and([$names('template=course, title%=risc'), $names('template=course, title~="teams agile"'), $names('template=course, title^=gest')])->toBe($before)
        ->and($before)->toBe([['risk'], ['agile'], ['risk']]);
});

it('indexes text fields with lynx:trigram', function () use ($trigramIndex): void {
    $this->artisan('lynx:trigram')->assertSuccessful();

    expect($trigramIndex('title'))->toBeTrue()
        ->and($trigramIndex('summary'))->toBeTrue()
        ->and($trigramIndex('price'))->toBeFalse();
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'trigram indexes are PostgreSQL only');

it('indexes new text fields when trigram is on', function () use ($trigramIndex): void {
    config(['lynx.trigram' => true]);
    $this->artisan('lynx:trigram')->assertSuccessful();
    lynx()->fields->add('subtitle', 'text', translatable: true);

    expect($trigramIndex('subtitle'))->toBeTrue();
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'trigram indexes are PostgreSQL only');

it('refuses lynx:trigram on other databases', function (): void {
    $this->artisan('lynx:trigram')->assertFailed();
})->skip(fn (): bool => DB::getDriverName() === 'pgsql', 'checks the refusal outside PostgreSQL');
