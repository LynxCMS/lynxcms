<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Tables;

/*
 * Exact and range clauses on translatable fields match the value visitors see (ADR-0005) through
 * one indexed lookup per language in the chain, instead of comparing a COALESCE.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('code', 'text', translatable: true);
    lynx()->fields->add('local', 'text', translatable: true, settings: ['fallback' => 'none']);
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'code', 'local']]);

    // [name, default code, pt code]: "beta" shows its own Portuguese code, "alpha" falls back.
    foreach ([['alpha', 'A-1', null], ['beta', 'B-1', 'B-2'], ['gamma', 'C-1', 'A-1']] as [$name, $code, $ptCode]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = ucfirst($name);
        $page->code = $code;
        $page->local = $code;

        if ($ptCode !== null) {
            $page->setLanguageValue('pt', 'code', $ptCode);
        }

        $page->save();
    }
});

$names = fn (string $selector, string $language = 'pt'): array => lynx()->language($language, fn (): array => lynx()->pages->find($selector.', sort=name')->map(fn (Page $page): string => (string) $page->name));

it('matches the value a language falls back to', function () use ($names): void {
    expect($names('template=course, code=A-1'))->toBe(['alpha', 'gamma'])
        ->and($names('template=course, code=B-1'))->toBe([])
        ->and($names('template=course, code=B-1', 'default'))->toBe(['beta']);
});

it('compares ranges and alternatives on the effective value', function () use ($names): void {
    expect($names('template=course, code>B-1'))->toBe(['beta'])
        ->and($names('template=course, code<=A-1'))->toBe(['alpha', 'gamma'])
        ->and($names('template=course, code=B-2|C-1'))->toBe(['beta']);
});

it('does not fall back for fields that opt out', function () use ($names): void {
    expect($names('template=course, local=A-1'))->toBe([])
        ->and($names('template=course, local=A-1', 'default'))->toBe(['alpha']);
});

it('ignores rows without a value when falling back', function () use ($names): void {
    $table = lynx()->fields->find('code')?->table();
    $pt = lynx()->languages->get('pt')->id;
    $alpha = lynx()->pages->get('/courses/alpha/');
    DB::table((string) $table)->insert(['page_id' => $alpha?->id, 'language_id' => $pt, 'data' => null]);

    expect($names('template=course, code=A-1'))->toBe(['alpha', 'gamma']);
});

it('keeps empty-value and contains clauses on the effective value', function () use ($names): void {
    expect($names('template=course, code='))->toBe([])
        ->and($names('template=course, code%=-2'))->toBe(['beta'])
        ->and($names('template=course, code!=A-1'))->toBe(['beta']);
});

it('gives field tables covering indexes on their value', function (): void {
    $columns = fn (string $field): array => collect(Schema::getIndexes((string) lynx()->fields->find($field)?->table()))
        ->firstWhere(fn (array $index): bool => str_ends_with($index['name'], '_data'))['columns'] ?? [];

    expect($columns('code'))->toBe(['language_id', 'data', 'page_id'])
        ->and($columns('price'))->toBe(['data', 'page_id']);
});

it('looks exact clauses up by language instead of comparing a COALESCE', function (): void {
    $sql = fn (string $selector): string => lynx()->language('pt', fn (): string => app(PageFinder::class)->compile($selector)->toSql());

    expect($sql('template=course, code=A-1'))->not->toContain('COALESCE')->toContain('union')
        ->and($sql('template=course, code%=A'))->toContain('COALESCE');
});

it('moves field tables made before covering indexes onto them', function (): void {
    $table = (string) lynx()->fields->find('code')?->table();
    $index = DB::getTablePrefix().$table;
    Schema::table($table, function (Blueprint $blueprint) use ($index): void {
        $blueprint->dropIndex("{$index}_data");
        $blueprint->index('language_id', "{$index}_lang");
        $blueprint->index('data', "{$index}_data");
    });

    (require dirname(__DIR__, 3).'/packages/core/database/migrations/2026_10_24_000000_cover_field_value_indexes.php')->up();

    expect(collect(Schema::getIndexes($table))->reject(fn (array $i): bool => $i['primary'])->pluck('columns', 'name')->all())
        ->toBe(["{$index}_data" => ['language_id', 'data', 'page_id']]);
});

it('indexes pages by template in their default order', function (): void {
    $indexes = collect(Schema::getIndexes(Tables::PAGES))->pluck('columns')->all();

    expect($indexes)->toContain(['template_id', 'sort', 'id'])->not->toContain(['template_id']);
});
