<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;
use LynxCms\Core\Schema\Draft\CompiledDraft;
use LynxCms\Core\Schema\Draft\DraftCompiler;
use LynxCms\Core\Schema\Draft\DraftError;

/*
 * A draft compiles into the items schema files hold (ADR-0116): uuids of what the site has are
 * kept, new items get new ones, and nothing that apply would refuse gets through.
 */

beforeEach(fn (): Lynx => courseSite());

/** @param  array<string, mixed>  $draft */
function compileDraft(array $draft): CompiledDraft
{
    return app(DraftCompiler::class)->compile($draft);
}

it('keeps the uuid of a field the site has', function (): void {
    expect(compileDraft(['fields' => ['price' => 'integer min:0']])->fields[0]['uuid'])->toBe(lynx()->fields->find('price')?->uuid);
});

it('keeps the uuid of a template the site has', function (): void {
    $template = compileDraft(['templates' => ['course' => ['fields' => ['title*']]]])->templates[0];

    expect($template['uuid'])->toBe(lynx()->templates->find('course')?->uuid);
});

it('gives a new field a new uuid', function (): void {
    expect(compileDraft(['fields' => ['seats' => 'integer']])->fields[0]['uuid'])->toBeUuid();
});

it('marks a required field with a star', function (): void {
    expect(compileDraft(['templates' => ['course' => ['fields' => ['title*', 'summary']]]])->templates[0]['fields'])
        ->toBe([['name' => 'title', 'required' => true], 'summary']);
});

it('creates a field a template defines inline', function (): void {
    $draft = compileDraft(['templates' => ['course' => ['fields' => ['title*', ['teacher' => 'text'], ['room*' => 'text']]]]]);

    expect(array_column($draft->fields, 'name'))->toBe(['teacher', 'room'])
        ->and($draft->templates[0]['fields'])->toBe([['name' => 'title', 'required' => true], 'teacher', ['name' => 'room', 'required' => true]]);
});

it('writes sections and tabs as schema files do', function (): void {
    $template = compileDraft(['templates' => ['course' => [
        'tabs' => ['media' => 'Media'],
        'fields' => ['title*', ['section' => 'Extra details', 'tab' => 'media', 'fields' => ['summary', 'related']]],
    ]]])->templates[0];

    expect($template['fields'][1])->toBe(['section' => 'extra-details', 'label' => 'Extra details', 'tab' => 'media', 'fields' => ['summary', 'related']])
        ->and($template['settings']['tabs'])->toBe([['key' => 'media', 'label' => 'Media']]);
});

it('turns family keys into template settings', function (): void {
    $settings = compileDraft(['templates' => [
        'courses' => ['url' => true, 'children' => ['course'], 'fields' => ['title*']],
        'course' => ['parents' => ['courses'], 'fields' => ['title*']],
        'site-settings' => ['url' => false, 'singleton' => true, 'fields' => ['title*']],
    ]])->templates;

    expect($settings[0]['settings'])->toBe(['has_url' => true, 'allow_children' => true, 'child_templates' => ['course']])
        ->and($settings[1]['settings'])->toBe(['parent_templates' => ['courses']])
        ->and($settings[2]['settings'])->toBe(['has_url' => false, 'singleton' => true]);
});

it('resolves a repeater\'s item fields from the draft or the site', function (): void {
    $draft = compileDraft(['fields' => [
        'speaker_name' => 'text',
        'speakers' => ['type' => 'repeater', 'fields' => ['speaker_name*', 'summary']],
    ]]);

    expect($draft->fields[1]['settings']['fields'])->toBe([['name' => 'speaker_name', 'required' => true], ['name' => 'summary']]);
});

it('refuses a template that names a field nobody defines', function (): void {
    compileDraft(['templates' => ['course' => ['fields' => ['title*', 'teacher']]]]);
})->throws(DraftError::class, 'Template [course] uses the field [teacher], which neither the draft nor the site defines.');

it('refuses a repeater whose item field nobody defines', function (): void {
    compileDraft(['fields' => ['speakers' => ['type' => 'repeater', 'fields' => ['speaker_name']]]]);
})->throws(DraftError::class, 'Field [speakers] uses the field [speaker_name], which neither the draft nor the site defines.');

it('refuses a repeater inside a repeater', function (): void {
    compileDraft(['fields' => [
        'note' => 'text',
        'inner' => ['type' => 'repeater', 'fields' => ['note']],
        'outer' => ['type' => 'repeater', 'fields' => ['inner']],
    ]]);
})->throws(DraftError::class, 'Field [outer]: [inner] holds item fields itself');

it('refuses to change the type of a field the site has', function (): void {
    compileDraft(['fields' => ['price' => 'decimal:2']]);
})->throws(DraftError::class, 'Field [price] is an integer field in the site, and the draft makes it decimal.');

it('keeps a field the site has shared when the draft leaves it unsaid', function (): void {
    lynx()->fields->add('code', 'text');

    expect(compileDraft(['fields' => ['code' => 'text max_length:20']])->fields[0]['translatable'])->toBeFalse();
});

it('lets a draft make a shared field of the site translatable', function (): void {
    lynx()->fields->add('code', 'text');

    expect(compileDraft(['fields' => ['code' => 'text translatable']])->fields[0]['translatable'])->toBeTrue();
});

it('refuses to make a translatable field of the site shared', function (): void {
    compileDraft(['fields' => ['summary' => 'textarea translatable:false']]);
})->throws(DraftError::class, 'with translatable changed');

it('refuses a template or field that a module owns', function (): void {
    lynx()->menus()->create('top', 'Top');

    compileDraft(['templates' => ['menu-item' => ['fields' => ['title*']]]]);
})->throws(DraftError::class, 'Template [menu-item] belongs to Menus');

it('refuses a family rule naming an unknown template', function (): void {
    compileDraft(['templates' => ['course' => ['parents' => ['curses'], 'fields' => ['title*']]]]);
})->throws(DraftError::class, 'Did you mean [courses]?');

it('refuses a field defined twice in different ways', function (): void {
    compileDraft(['fields' => ['teacher' => 'text'], 'templates' => ['course' => ['fields' => ['title*', ['teacher' => 'textarea']]]]]);
})->throws(DraftError::class, 'Field [teacher] is defined twice');

/*
 * A template the site has keeps the settings each field has there (label, description, tab,
 * width...) when the draft only names the field, as labels and field settings are kept.
 */

function courseWithUseSettings(): void
{
    lynx()->templates->update('course', ['fields' => [
        ['name' => 'title', 'required' => true],
        ['name' => 'summary', 'description' => 'One line for listings.', 'width' => 50],
        'body', 'price', 'related',
    ]]);
}

/** @return list<mixed> the compiled field list of course */
function courseEntries(array $fields): array
{
    return compileDraft(['templates' => ['course' => ['fields' => $fields]]])->templates[0]['fields'];
}

it('keeps the settings a field has on a template when the draft only names it', function (): void {
    courseWithUseSettings();

    expect(courseEntries(['title', 'summary', 'body', 'price', 'related']))->toBe([
        ['name' => 'title', 'required' => true],
        ['name' => 'summary', 'description' => 'One line for listings.', 'width' => 50],
        'body', 'price', 'related',
    ]);
});

it('lets the draft change one setting of a field on a template and keeps the others', function (): void {
    courseWithUseSettings();

    expect(courseEntries(['title*', ['name' => 'summary', 'width' => 100], 'body', 'price', 'related'])[1])
        ->toBe(['name' => 'summary', 'description' => 'One line for listings.', 'width' => 100]);
});

it('clears a setting of a field on a template that the draft sets to null', function (): void {
    courseWithUseSettings();

    expect(courseEntries(['title*', ['name' => 'summary', 'description' => null], 'body', 'price', 'related'])[1])
        ->toBe(['name' => 'summary', 'width' => 50]);
});

it('places a field where the draft puts it, not in its old section', function (): void {
    lynx()->templates->update('course', ['fields' => [
        'title', ['section' => 'extra', 'label' => 'Extra', 'fields' => ['summary']], 'body', 'price', 'related',
    ]]);

    expect(courseEntries(['title', 'summary', 'body', 'price', 'related']))->toBe(['title', 'summary', 'body', 'price', 'related']);
});
