<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Templates\FieldDisplay;

/*
 * Locked fields (ADR-0149): the site's code fills them, and editors, MCP clients and the agent may
 * not change them. A field is locked on its own (every template) or on one template. Publishing a
 * draft or restoring a revision keeps a locked field's live value.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

function lockedAlpha(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

function lockPrice(): void
{
    lynx()->fields->update('price', ['settings' => ['locked' => true]]);
}

/** @param  array<string, mixed>  $values */
function saveAs(Actor $actor, array $values): mixed
{
    return app(ActionRunner::class)->actingAs($actor, fn (): ActionResult => lynx()->run(SavePage::class, ['id' => lockedAlpha()->id, 'values' => $values]));
}

it('refuses people, MCP clients and the agent who change a locked field, and saves their other fields', function (Actor $actor): void {
    lockPrice();

    expect(fn (): mixed => saveAs($actor, ['price' => 99]))->toThrow(ValidationException::class);

    saveAs($actor, ['title' => ['default' => 'Alpha, renamed']]);

    expect(lockedAlpha()->get('price'))->toBeNull()->and((string) lockedAlpha()->get('title'))->toBe('Alpha, renamed');
})->with([
    'a person' => [Actor::user(1)],
    'an MCP client' => [Actor::mcp(1)],
    'the agent' => [Actor::ai(1)],
]);

it('lets the site\'s code write a locked field', function (Actor $actor): void {
    lockPrice();

    saveAs($actor, ['price' => 42]);

    expect(lockedAlpha()->get('price'))->toBe(42);
})->with([
    'the command line' => [Actor::cli()],
    'the system' => [Actor::system()],
]);

it('locks a field on one template only', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'locked' => true], 'related']]);

    expect(fn (): mixed => saveAs(Actor::user(1), ['price' => 99]))->toThrow(ValidationException::class)
        ->and(lynx()->templates->fields('course')['price']->isLocked())->toBeTrue()
        ->and(lynx()->templates->fields('courses')->has('price'))->toBeFalse();
});

it('reads the old editor: locked as open and locked', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'editor' => 'locked'], 'related']]);
    $price = lynx()->templates->fields('course')['price'];

    expect($price->isLocked())->toBeTrue()->and($price->display())->toBe(FieldDisplay::Open)->and($price->isEditable())->toBeFalse();
});

it('combines locked with a closed display', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'editor' => 'collapsed', 'locked' => true], 'related']]);
    $price = lynx()->templates->fields('course')['price'];

    expect($price->display())->toBe(FieldDisplay::Collapsed)->and($price->isLocked())->toBeTrue();
});

it('keeps the live value of a locked field when a draft is published', function (): void {
    lockPrice();
    saveAs(Actor::system(), ['price' => 10]);
    app(ActionRunner::class)->actingAs(Actor::user(1), fn () => lynx()->pages->saveDraft(lockedAlpha(), ['title' => ['default' => 'Alpha draft']]));
    saveAs(Actor::system(), ['price' => 20]);

    app(ActionRunner::class)->actingAs(Actor::user(1), fn (): ActionResult => lynx()->run(PublishDraft::class, ['page' => lockedAlpha()->id]));

    expect(lockedAlpha()->get('price'))->toBe(20)->and((string) lockedAlpha()->get('title'))->toBe('Alpha draft');
});

it('keeps the live value of a locked field when a revision is restored', function (): void {
    lockPrice();
    saveAs(Actor::system(), ['price' => 10]);
    saveAs(Actor::user(1), ['title' => ['default' => 'Alpha, renamed']]);
    $older = lynx()->pages->revisions(lockedAlpha())[1]['id'];
    saveAs(Actor::system(), ['price' => 30]);

    app(ActionRunner::class)->actingAs(Actor::user(1), fn (): ActionResult => lynx()->run(RestoreRevision::class, ['page' => lockedAlpha()->id, 'revision' => $older]));

    expect(lockedAlpha()->get('price'))->toBe(30);
});

it('tells agents which fields are locked', function (): void {
    lockPrice();
    $course = collect(app(AgentToolkit::class)->describeSchema()['templates'] ?? [])->firstWhere('name', 'course');

    expect(collect($course['fields'] ?? [])->firstWhere('name', 'price')['locked'] ?? null)->toBeTrue()
        ->and(SavePage::description())->toContain('locked');
});

it('refuses lazy loading on a field that is always open or hidden', function (string $display): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'editor' => $display, 'lazy' => true], 'related']]);
})->with(['open', 'hidden'])->throws(ValidationException::class);

it('loads a closed field when it is opened', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'editor' => 'collapsed', 'lazy' => true], 'related']]);

    expect(lynx()->templates->fields('course')['price']->isLazy())->toBeTrue();
});

it('refuses to lock a field a template requires, and to require a locked field', function (): void {
    expect(fn () => lynx()->fields->update('title', ['settings' => ['locked' => true]]))->toThrow(ValidationException::class);

    lockPrice();

    expect(fn () => lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'required' => true], 'related']]))
        ->toThrow(ValidationException::class);
});

it('offers a field that opens and closes', function (): void {
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', ['name' => 'price', 'editor' => 'open_collapsible', 'lazy' => true], 'related']]);
    $price = lynx()->templates->fields('course')['price'];

    expect($price->display())->toBe(FieldDisplay::OpenCollapsible)->and($price->display()->isCollapsible())->toBeTrue()
        ->and($price->display()->startsCollapsed(true))->toBeFalse();
});
