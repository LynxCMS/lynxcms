<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use InvalidArgumentException;
use LynxCms\Forms\Builder\PageForms;
use LynxCms\Forms\Definition\Form;

/**
 * The forms a site has, by key: code forms the app registers (ADR-0052), then the builder's
 * published forms (ADR-0053). A code form wins when both use a key.
 *
 * @api
 */
final class FormRegistry
{
    /** @var array<string, Form> */
    private array $forms = [];

    public function __construct(private readonly PageForms $pageForms) {}

    /** @param class-string<Form>|Form ...$forms */
    public function register(string|Form ...$forms): void
    {
        foreach ($forms as $form) {
            $form = is_string($form) ? app($form) : $form;

            if (! $form instanceof Form || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $form->key()) !== 1) {
                throw new InvalidArgumentException('Forms need a key of lowercase letters, digits, dashes and underscores.');
            }

            $this->forms[$form->key()] = $form;
        }
    }

    public function get(string $key): ?Form
    {
        return $this->forms[$key] ?? $this->pageForms->find($key);
    }

    /** A form registered in code, not the builder's. */
    public function codeForm(string $key): ?Form
    {
        return $this->forms[$key] ?? null;
    }

    /** @return array<string, Form> code forms, then the builder's */
    public function all(): array
    {
        return $this->forms + $this->pageForms->all();
    }
}
