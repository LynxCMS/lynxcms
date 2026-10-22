<?php

declare(strict_types=1);

namespace LynxCms\Forms\Builder;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormSchema;

/**
 * Refuses form pages that would make a broken form (ADR-0053), on the block that is wrong:
 * names that are repeated or not usable, choices without options, conditions on fields that do
 * not come before, indented blocks outside a group, groups or steps inside a group, a published
 * form without fields, and a name a code form already uses. Runs after pages.save, so a refusal
 * rolls the save back.
 *
 * @internal
 */
final readonly class PageFormValidator
{
    private const string FIELD_NAME = '/^[a-z][a-z0-9_]*$/';

    private const array CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    public function __construct(private FormRegistry $forms) {}

    public function check(mixed $page): void
    {
        if (! $page instanceof Page || $page->template()->name !== FormSchema::FORM_TEMPLATE) {
            return;
        }

        $errors = $this->errors($page);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, string>
     */
    public function errors(Page $page): array
    {
        $errors = [];
        $names = [];
        $inGroup = false;
        $fieldCount = 0;

        foreach (PageFormCompiler::blocks($page) as $block) {
            $key = 'values.'.FormSchema::FIELDS.'.'.$block['index'];

            if ($block['depth'] > 0) {
                $problem = match (true) {
                    ! $inGroup => 'Indented blocks belong to a group: add a group above it, or outdent it.',
                    in_array($block['type'], ['group', 'step'], true) => 'A group cannot hold a group or a step.',
                    default => null,
                };

                if ($problem !== null) {
                    $errors[$key.'.type'] = $problem;

                    continue;
                }
            } else {
                $inGroup = $block['type'] === 'group';
            }

            if ($block['type'] === 'step') {
                continue;
            }

            $fieldCount++;
            $errors += $this->blockErrors($block, $key, $names);
        }

        if ($fieldCount === 0 && ! $page->hasStatus(PageStatus::UNPUBLISHED)) {
            $errors['values.'.FormSchema::FIELDS] = 'Add at least one field before publishing the form.';
        }

        if ($page->name() !== '' && $this->forms->codeForm($page->name()) !== null) {
            $errors['names.'.lynx()->languages->default()->code] = "A form defined in code already uses the name [{$page->name()}].";
        }

        return $errors;
    }

    /**
     * @param  array{index: int, type: string, depth: int, values: array<string, mixed>}  $block
     * @param  list<string>  $names  names of the blocks before, extended with this one
     * @return array<string, string>
     */
    private function blockErrors(array $block, string $key, array &$names): array
    {
        $errors = [];
        $name = PageFormCompiler::nameOf($block);
        $showIf = trim((string) ($block['values'][FormSchema::SHOW_IF] ?? ''));

        if (preg_match(self::FIELD_NAME, $name) !== 1) {
            $errors[$key.'.'.FormSchema::NAME] = "[{$name}] cannot be a field name: use lowercase letters, digits and underscores, starting with a letter.";
        } elseif (in_array($name, $names, true)) {
            $errors[$key.'.'.FormSchema::NAME] = "Another field is already named [{$name}]; give this one its own name.";
        }

        if (in_array($block['type'], self::CHOICE_TYPES, true) && ($block['values'][FormSchema::OPTIONS] ?? []) === []) {
            $errors[$key.'.'.FormSchema::OPTIONS] = 'Add at least one option.';
        }

        if ($showIf !== '' && ! in_array($showIf, $names, true)) {
            $errors[$key.'.'.FormSchema::SHOW_IF] = "There is no field [{$showIf}] before this one.";
        }

        [$operator, $values] = PageFormCompiler::parseCondition((string) ($block['values'][FormSchema::SHOW_IF_VALUES] ?? ''));

        if (in_array($operator, ['<', '<=', '>', '>='], true) && ! is_numeric($values[0] ?? null)) {
            $errors[$key.'.'.FormSchema::SHOW_IF_VALUES] = 'Compare with a number, e.g. ">= 18".';
        }

        $names[] = $name;

        return $errors;
    }
}
