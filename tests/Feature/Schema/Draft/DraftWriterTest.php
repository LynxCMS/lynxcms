<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;
use LynxCms\Core\Schema\Draft\DraftBuilder;
use LynxCms\Core\Schema\Draft\DraftCompiler;
use LynxCms\Core\Schema\Draft\DraftOptions;
use LynxCms\Core\Schema\Draft\DraftReader;
use LynxCms\Core\Schema\Draft\DraftWriter;

/*
 * lynx:draft --from-site writes the site's own schema as a draft (ADR-0116): the short form where
 * a field fits it, the long form otherwise, and nothing Lynx or a module owns. Building that
 * draft changes nothing.
 */

beforeEach(fn (): Lynx => courseSite());

/** @return list<string> the plan of building a draft written from the site */
function roundTripPlan(): array
{
    $draft = app(DraftCompiler::class)->compile(app(DraftReader::class)->parse(app(DraftWriter::class)->write()));

    return app(DraftBuilder::class)->plan($draft, new DraftOptions(writeFiles: false))->lines();
}

it('writes a field in the short form when it fits, translatable by default', function (): void {
    expect(app(DraftWriter::class)->write())->toContain('summary: textarea')->not->toContain('translatable')
        ->toContain('body: \'textarea:html\'');
});

it('writes shared for a field that could be translatable but isn\'t', function (): void {
    lynx()->fields->add('code', 'text');

    expect(app(DraftWriter::class)->write())->toContain('code: \'text shared\'');
});

it('builds back a site with a shared text field', function (): void {
    lynx()->fields->add('code', 'text', settings: ['description' => 'Internal code']);
    lynx()->fields->add('motto', 'textarea', translatable: true, settings: ['format' => 'html']);

    expect(roundTripPlan())->toBe([]);
});

it('writes a template with its required fields starred', function (): void {
    expect(app(DraftWriter::class)->write())->toContain("course:\n    fields:\n      - 'title*'\n      - summary\n      - body\n      - price\n      - related\n");
});

it('leaves out what Lynx and the modules own', function (): void {
    lynx()->menus()->create('top', 'Top');

    expect(app(DraftWriter::class)->write())->not->toContain('menu-item')->not->toContain('menu_page');
});

it('builds back into the same site', function (): void {
    expect(roundTripPlan())->toBe([]);
});

it('builds back a site with labels, choices, repeaters, sections, tabs and family rules', function (): void {
    lynx()->fields->add('level', 'choice', label: 'Course level', settings: ['options' => [['value' => 'easy', 'label' => 'Easy'], ['value' => 'hard', 'label' => 'Very hard']], 'multiple' => true]);
    lynx()->fields->add('speaker_name', 'text', settings: ['max_length' => 80, 'description' => 'Full name, as printed']);
    lynx()->fields->add('speakers', 'repeater', settings: ['fields' => [['name' => 'speaker_name', 'required' => true], ['name' => 'summary', 'width' => 50]], 'max' => 6]);
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'extra', 'label' => 'Extra']],
        'fields' => [['name' => 'title', 'required' => true], 'summary', ['section' => 'people', 'label' => 'The people', 'tab' => 'extra', 'fields' => ['speakers', ['name' => 'level', 'label' => 'Level']]]],
        'parent_templates' => ['courses'],
    ]);
    lynx()->templates->update('courses', ['allow_children' => true, 'child_templates' => ['course'], 'has_url' => true]);

    expect(roundTripPlan())->toBe([]);
});
