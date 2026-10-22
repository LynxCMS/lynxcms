<?php

declare(strict_types=1);

namespace LynxCms\Forms\Builder;

use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormStep;

/**
 * A form made in the builder (ADR-0053): the definition PageFormCompiler reads from a form page,
 * used by everything code forms use (the component, submit, inbox, mail).
 *
 * @api
 */
final class PageForm extends Form
{
    /**
     * @param  string|array<string, string>  $label
     * @param  list<FormField>  $fields
     * @param  list<FormStep>  $steps
     * @param  list<string>|null  $recipients
     * @param  array<string, string|array<string, string>|null>  $texts  success, error and auto_reply
     */
    public function __construct(
        public readonly int $pageId,
        private readonly string $key,
        private readonly string|array $label,
        private readonly array $fields,
        private readonly array $steps,
        private readonly ?array $recipients,
        private readonly array $texts,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string|array
    {
        return $this->label;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function steps(): array
    {
        return $this->steps;
    }

    public function recipients(): ?array
    {
        return $this->recipients;
    }

    public function successMessage(): string|array
    {
        return $this->texts['success'] ?? parent::successMessage();
    }

    public function errorMessage(): string|array
    {
        return $this->texts['error'] ?? parent::errorMessage();
    }

    public function autoReply(): string|array|null
    {
        return $this->texts['auto_reply'] ?? null;
    }
}
