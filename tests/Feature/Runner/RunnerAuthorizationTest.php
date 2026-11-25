<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Permissions\Authorizer;

/*
 * The runner checks an outer action's permission before anything else: before it validates the
 * input, so a refused actor learns nothing about the schema from validation messages, and in a
 * way that a failed transaction can't switch off for the actions that follow.
 */

beforeEach(function (): void {
    app(Authorizer::class)->openForTests(false);
    courseSite();
});

it('refuses an actor before it validates the input', function (): void {
    lynx()->actingAs(Actor::user(7), fn (): ActionResult => lynx()->run(CreateField::class, ['name' => 'summary', 'type' => 'text']));
})->throws(AuthorizationException::class);

it('keeps checking permissions after a transaction fails to start', function (): void {
    $failed = false;
    Event::listen(TransactionBeginning::class, function () use (&$failed): void {
        if (! $failed) {
            $failed = true;
            throw new RuntimeException('The database went away.');
        }
    });

    try {
        lynx()->fields->add('notes', 'text');
    } catch (RuntimeException) {
        // Expected: the runner rolled back what had begun.
    }

    expect($failed)->toBeTrue()
        ->and(fn (): ActionResult => lynx()->actingAs(Actor::user(7), fn (): ActionResult => lynx()->run(CreateField::class, ['name' => 'secret', 'type' => 'text'])))
        ->toThrow(AuthorizationException::class);
});
