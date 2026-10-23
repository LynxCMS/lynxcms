<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Support\Tables;

beforeEach(fn (): Lynx => installLynx());

it('creates field tables at runtime', function (): void {
    lynx()->fields->add('body', 'textarea', translatable: true);
    lynx()->fields->add('price', 'integer');
    lynx()->fields->add('related', 'page');

    expect(Schema::getColumnListing('lynx_f_body'))->toEqualCanonicalizing(['page_id', 'language_id', 'data'])
        ->and(Schema::getColumnListing('lynx_f_price'))->toEqualCanonicalizing(['page_id', 'data'])
        ->and(Schema::getColumnListing('lynx_f_related'))->toEqualCanonicalizing(['page_id', 'sort', 'data']);
});

it('rolls back a failed field creation completely', function (): void {
    lynx()->hook(CreateField::class)->after(fn () => throw new RuntimeException('after hook failed'));

    expect(fn () => lynx()->fields->add('doomed', 'text'))->toThrow(RuntimeException::class)
        ->and(lynx()->fields->find('doomed'))->toBeNull()
        ->and(Schema::hasTable('lynx_f_doomed'))->toBeFalse();
});

it('rejects a field whose table name is already taken', function (): void {
    Schema::create('lynx_f_clash', fn (Blueprint $table) => $table->integer('id'));

    expect(fn () => lynx()->fields->add('clash', 'text'))->toThrow(ValidationException::class)
        ->and(lynx()->fields->find('clash'))->toBeNull();
});

it('keeps core tables out of the field table namespace', function (): void {
    $core = new ReflectionClass(Tables::class)->getConstants();
    unset($core['FIELD_PREFIX']);

    foreach (array_filter($core, is_string(...)) as $table) {
        expect(str_starts_with($table, Tables::FIELD_PREFIX))->toBeFalse("{$table} could clash with a field table");
    }
});

it('rejects reserved names, unknown types and untranslatable types', function (): void {
    expect(fn () => lynx()->fields->add('parent', 'text'))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->add('x', 'nope'))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->add('cost', 'integer', translatable: true))->toThrow(ValidationException::class);
});

it('dry-runs a field creation without leaving a table', function (): void {
    $result = lynx()->fields->add('subtitle', 'text', translatable: true, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and(Schema::hasTable('lynx_f_subtitle'))->toBeFalse();
});

it('creates templates with ordered fields and copies them with from', function (): void {
    lynx()->fields->add('body', 'textarea', translatable: true);
    lynx()->fields->add('price', 'integer');
    lynx()->templates->add('course', fields: [['name' => 'title', 'required' => true], 'body', 'price']);
    lynx()->fields->add('client', 'text');

    lynx()->templates->add('consulting', fields: ['client'], from: 'course');

    expect(lynx()->templates->fields('consulting')->keys()->all())->toBe(['title', 'body', 'price', 'client'])
        ->and(lynx()->templates->fields('consulting')->get('title')->required())->toBeTrue();
});

it('keeps values when a field leaves a template', function (): void {
    lynx()->fields->add('price', 'integer');
    lynx()->templates->add('course', fields: ['title', 'price']);
    $page = lynx()->pages->new('course');
    $page->name = 'x';
    $page->title = 'X';
    $page->price = 100;
    $page->save();

    lynx()->templates->update('course', ['remove' => ['price']]);

    expect(DB::table('lynx_f_price')->count())->toBe(1);
});

it('describes field types for agents', function (): void {
    $types = lynx()->fields->types();

    expect($types)->toHaveKeys(['text', 'textarea', 'integer', 'toggle', 'page', 'component'])
        ->and($types['textarea']['settings'])->toHaveKey('format');
});

it('refuses to delete a template in use', function (): void {
    lynx()->templates->add('course', fields: ['title']);
    $page = lynx()->pages->new('course');
    $page->title = 'X';
    $page->save();

    expect(fn () => lynx()->templates->delete('course'))->toThrow(ValidationException::class);
});

it('names field tables lynx_f_{name} and fits the longest name in PostgreSQL identifiers', function (): void {
    $longest = 'a'.str_repeat('b', Tables::maxFieldNameLength(DB::getTablePrefix()) - 1);

    lynx()->fields->add($longest, 'text', translatable: true);

    expect(Schema::hasTable('lynx_f_'.$longest))->toBeTrue()
        ->and(strlen(DB::getTablePrefix().'lynx_f_'.$longest.'_lang'))->toBeLessThanOrEqual(63)
        ->and(fn () => lynx()->fields->add($longest.'c', 'text'))->toThrow(ValidationException::class);

    // A second long name sharing everything but the last character gets its own table and indexes.
    lynx()->fields->add(substr($longest, 0, -1).'c', 'text', translatable: true);
    expect(Schema::hasTable('lynx_f_'.substr($longest, 0, -1).'c'))->toBeTrue();
});

it('says why a reserved name is refused', function (): void {
    expect(fn () => lynx()->fields->add('status', 'choice'))
        ->toThrow(ValidationException::class, 'The name "status" is reserved, because pages and selectors already use it. Choose another name.');
});
