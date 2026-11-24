<?php

declare(strict_types=1);

use LynxCms\Admin\Fields\FieldFormTabs;

/*
 * The field form's tabs (Basics, Settings, Access, Advanced and, for repeaters and matrices, the
 * builder and its limits), and the tab each error key belongs to, so the tab bar marks it.
 */

it('gives a plain field Basics, Settings, Access, Advanced, and Preview', function (): void {
    expect(new FieldFormTabs(hasItemBuilder: false, isNew: false, hasSettings: true, hasAccess: true)->all())
        ->toBe(['field', 'settings', 'access', 'advanced', 'preview']);
});

it('leaves out Settings and Access when there is nothing to show on them', function (): void {
    expect(new FieldFormTabs(hasItemBuilder: false, isNew: false, hasSettings: false, hasAccess: false)->all())
        ->toBe(['field', 'advanced', 'preview']);
});

it('opens an existing repeater on its builder, and a new one on Basics', function (): void {
    expect(new FieldFormTabs(hasItemBuilder: true, isNew: false, hasSettings: false, hasAccess: true)->all())
        ->toBe(['builder', 'preview', 'field', 'limits', 'access', 'advanced'])
        ->and(new FieldFormTabs(hasItemBuilder: true, isNew: true, hasSettings: true, hasAccess: false)->all())
        ->toBe(['field', 'builder', 'preview', 'limits', 'settings', 'advanced']);
});

it('marks the tab an error belongs to', function (string $key, string $tab): void {
    expect(new FieldFormTabs(hasItemBuilder: true, isNew: false, hasSettings: true, hasAccess: true)->forError($key))->toBe($tab);
})->with([
    ['name', 'field'],
    ['settings.description', 'field'],
    ['settings.max_length', 'settings'],
    ['settings.fields.0.name', 'builder'],
    ['settings.max', 'limits'],
    ['settings.access.edit.0', 'access'],
    ['settings.locked', 'advanced'],
    ['settings.autoload', 'advanced'],
]);

it('puts a setting error on Basics when the field has no Settings tab', function (): void {
    expect(new FieldFormTabs(hasItemBuilder: false, isNew: false, hasSettings: false, hasAccess: false)->forError('settings.max_length'))->toBe('field');
});
