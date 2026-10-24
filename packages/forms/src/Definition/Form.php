<?php

declare(strict_types=1);

namespace LynxCms\Forms\Definition;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Models\Submission;

/**
 * A form defined in code (ADR-0052): its fields, optional steps, who hears about submissions and
 * what visitors read. Texts are a string, or a map of language codes to text, read with fallback.
 * Register it with lynx()->forms()->register(MyForm::class).
 *
 * @api
 */
abstract class Form
{
    /** A stable key, e.g. "quote": submissions and the admin refer to the form by it. */
    abstract public function key(): string;

    /** @return string|array<string, string> */
    abstract public function label(): string|array;

    /** @return list<FormField> */
    abstract public function fields(): array;

    /** @return list<FormStep> none for a one-page form */
    public function steps(): array
    {
        return [];
    }

    /** @return string|array<string, string> by default lynx-forms::forms.sent, in the visitor's language */
    public function successMessage(): string|array
    {
        return __('lynx-forms::forms.sent');
    }

    /** @return string|array<string, string> shown when sending fails for a reason other than the fields */
    public function errorMessage(): string|array
    {
        return __('lynx-forms::forms.failed');
    }

    /** @return string|array<string, string>|null a confirmation mailed to the replyTo() email field, in the visitor's language */
    public function autoReply(): string|array|null
    {
        return null;
    }

    /**
     * Who is mailed about a submission: these addresses, or null for the users whose roles grant
     * forms.view.
     *
     * @return list<string>|null
     */
    public function recipients(): ?array
    {
        return null;
    }

    /**
     * Whether only signed-in users may answer, once each, as with a survey for members. Guests are
     * asked to sign in, and a second answer from the same account is refused (ADR-0076).
     */
    public function oncePerUser(): bool
    {
        return false;
    }

    /** Runs after a submission is stored and committed. */
    public function afterSubmit(Submission $submission): void {}

    public function text(string $which): LocalizedText
    {
        return LocalizedText::from(match ($which) {
            'label' => $this->label(),
            'success' => $this->successMessage(),
            default => $this->errorMessage(),
        });
    }

    public function field(string $name): ?FormField
    {
        foreach ($this->fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** The email field answers go to, if any. */
    public function replyToField(): ?FormField
    {
        foreach ($this->fields() as $field) {
            if ($field->isReplyTo) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The fields as stored with each submission, so later changes to the form keep old
     * submissions readable.
     *
     * @return list<array<string, mixed>>
     */
    public function snapshot(): array
    {
        return array_map(fn (FormField $field): array => $field->snapshot(), $this->fields());
    }
}
