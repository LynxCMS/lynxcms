<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ActionTool;

/*
 * update() on templates, fields and languages takes the action's input keys, in snake_case. A key
 * the action doesn't know, such as hasUrl copied from add()'s named arguments, used to be ignored
 * without a word; it is refused now, with the snake_case key when that is one the action knows.
 */

beforeEach(function (): void {
    courseSite();
});

/** The validation errors a callback throws, keyed by input key. */
function unknownInputErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('refuses a camelCase key on a template and names the snake_case one', function (): void {
    $errors = unknownInputErrors(fn () => lynx()->templates->update('course', ['hasUrl' => false]));

    expect($errors)->toHaveKey('hasUrl')
        ->and($errors['hasUrl'][0])->toContain('has_url')
        ->and(lynx()->templates->find('course')?->hasUrl())->toBeTrue();
});

it('refuses a key no action input has, on fields and languages', function (): void {
    expect(unknownInputErrors(fn () => lynx()->fields->update('summary', ['maxLength' => 5])))->toHaveKey('maxLength')
        ->and(unknownInputErrors(fn () => lynx()->languages->update('pt', ['nmae' => 'Português'])))->toHaveKey('nmae');
});

it('refuses unknown keys in a dry run too', function (): void {
    expect(unknownInputErrors(fn () => lynx()->templates->update('course', ['singletn' => true], dryRun: true)))->toHaveKey('singletn');
});

it('still takes the keys the action knows', function (): void {
    lynx()->templates->update('course', ['has_url' => false]);
    lynx()->fields->update('summary', ['settings' => ['autoload' => true]]);

    expect(lynx()->templates->find('course')?->hasUrl())->toBeFalse();
});

it('returns the refusal to MCP clients', function (): void {
    LynxServer::tool(new ActionTool(UpdateTemplate::class), ['template' => 'course', 'hasUrl' => false])
        ->assertHasErrors();
});
