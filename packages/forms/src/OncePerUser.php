<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Models\Submission;

/**
 * Forms members answer once each (Form::oncePerUser(), ADR-0076): why an actor may not answer,
 * for forms.submit to refuse and the form component to say instead of showing the form.
 *
 * @internal
 */
final class OncePerUser
{
    /** The translation key of the reason, or null when the actor may answer. */
    public static function refusal(Form $form, Actor $actor): ?string
    {
        if (! $form->oncePerUser()) {
            return null;
        }

        if ($actor->kind !== Actor::USER || $actor->id === null) {
            return 'lynx-forms::forms.sign_in_to_answer';
        }

        return Submission::query()->where('form', $form->key())->fromUser($actor->id)->exists()
            ? 'lynx-forms::forms.already_answered'
            : null;
    }
}
