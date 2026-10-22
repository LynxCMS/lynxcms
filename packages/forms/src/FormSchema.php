<?php

declare(strict_types=1);

namespace LynxCms\Forms;

/**
 * Templates and fields of the form builder (ADR-0053): forms are pages under a hidden "_forms"
 * page, and their fields are blocks of the form_fields matrix, one block type per field kind.
 * A group block takes the blocks indented under it; a step block starts a new step.
 *
 * @api
 */
final class FormSchema
{
    public const string ROOT_TEMPLATE = 'forms';

    public const string FORM_TEMPLATE = 'form';

    public const string ROOT_NAME = '_forms';

    /** The page reference site templates add to pick a form. */
    public const string PICKER = 'form';

    public const string FIELDS = 'form_fields';

    public const string LABEL = 'form_label';

    public const string NAME = 'form_name';

    public const string HELP = 'form_help';

    public const string REQUIRED = 'form_required';

    public const string WIDTH = 'form_width';

    public const string OPTIONS = 'form_options';

    public const string MAX = 'form_max';

    public const string EXTENSIONS = 'form_extensions';

    public const string MAX_KB = 'form_max_kb';

    public const string MAX_FILES = 'form_max_files';

    public const string MAX_ROWS = 'form_max_rows';

    public const string SHOW_IF = 'form_show_if';

    public const string SHOW_IF_VALUES = 'form_show_if_values';

    public const string RECIPIENTS = 'form_recipients';

    public const string SUCCESS = 'form_success';

    public const string ERROR = 'form_error';

    public const string AUTO_REPLY = 'form_auto_reply';

    /** Form names, which are also their keys: "contact", "newsletter-signup". */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /** Block types that are form fields, as opposed to "step". */
    public const array FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'number', 'date', 'select', 'radio', 'checkboxes', 'checkbox', 'file', 'group'];

    /**
     * Fields to create, subfields before the matrix that uses them: name => [type, translatable, label, settings].
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: array<string, mixed>}>
     */
    public static function fields(): array
    {
        return [
            self::LABEL => ['text', true, 'Label', []],
            self::NAME => ['text', false, 'Name (for developers)', []],
            self::HELP => ['text', true, 'Help text', []],
            self::REQUIRED => ['toggle', false, 'Required', []],
            self::WIDTH => ['integer', false, 'Width (100, 50, 33 or 25)', []],
            self::OPTIONS => ['options', false, 'Options', []],
            self::MAX => ['integer', false, 'Maximum (characters, or the largest number)', []],
            self::EXTENSIONS => ['text', false, 'Allowed file types (comma separated)', []],
            self::MAX_KB => ['integer', false, 'Largest file (KB)', []],
            self::MAX_FILES => ['integer', false, 'Most files', []],
            self::MAX_ROWS => ['integer', false, 'Most rows', []],
            self::SHOW_IF => ['text', false, 'Show only when field', []],
            self::SHOW_IF_VALUES => ['text', false, 'is one of (comma separated), compares as >= 18, or yes / no', []],
            self::FIELDS => ['matrix', false, 'Fields', ['types' => self::blockTypes(), 'max_depth' => 1]],
            self::RECIPIENTS => ['text', false, 'Send submissions to (emails, comma separated)', []],
            self::SUCCESS => ['textarea', true, 'Message after sending', []],
            self::ERROR => ['textarea', true, 'Message when sending fails', []],
            self::AUTO_REPLY => ['textarea', true, 'Confirmation mailed to the visitor', []],
            self::PICKER => ['page', false, 'Form', ['multiple' => false, 'templates' => [self::FORM_TEMPLATE]]],
        ];
    }

    /**
     * Templates to create: name => [label, fields, singleton, family settings].
     *
     * @return array<string, array{0: string, 1: list<string|array<string, mixed>>, 2: bool, 3: array<string, mixed>}>
     */
    public static function templates(): array
    {
        return [
            self::ROOT_TEMPLATE => ['Forms', ['title'], true, ['child_templates' => [self::FORM_TEMPLATE]]],
            self::FORM_TEMPLATE => ['Form', [['name' => 'title', 'required' => true], self::FIELDS, self::RECIPIENTS, self::SUCCESS, self::ERROR, self::AUTO_REPLY], false, ['allow_children' => false, 'parent_templates' => [self::ROOT_TEMPLATE]]],
        ];
    }

    /**
     * The form_fields matrix's block types: each shows the settings that apply to it.
     *
     * @return list<array<string, mixed>>
     */
    public static function blockTypes(): array
    {
        $common = [['name' => self::LABEL, 'required' => true], ['name' => self::NAME, 'width' => 50], ['name' => self::WIDTH, 'width' => 50], ['name' => self::HELP], ['name' => self::REQUIRED]];
        $condition = [['name' => self::SHOW_IF, 'width' => 50], ['name' => self::SHOW_IF_VALUES, 'width' => 50]];
        $type = fn (string $slug, string $label, array $extra = []): array => ['slug' => $slug, 'label' => $label, 'fields' => [...$common, ...$extra, ...$condition], 'item_label' => '{'.self::LABEL.'}'];

        return [
            $type('text', 'Text', [['name' => self::MAX]]),
            $type('email', 'Email'),
            $type('tel', 'Phone'),
            $type('textarea', 'Long text', [['name' => self::MAX]]),
            $type('number', 'Number', [['name' => self::MAX]]),
            $type('date', 'Date'),
            $type('select', 'Dropdown', [['name' => self::OPTIONS]]),
            $type('radio', 'Choose one', [['name' => self::OPTIONS]]),
            $type('checkboxes', 'Choose several', [['name' => self::OPTIONS]]),
            $type('checkbox', 'Tick box'),
            $type('file', 'Files', [['name' => self::EXTENSIONS, 'width' => 50], ['name' => self::MAX_KB, 'width' => 25], ['name' => self::MAX_FILES, 'width' => 25]]),
            ['slug' => 'group', 'label' => 'Repeating group (indent its fields under it)', 'fields' => [['name' => self::LABEL, 'required' => true], ['name' => self::NAME, 'width' => 50], ['name' => self::MAX_ROWS, 'width' => 50], ...$condition], 'item_label' => '{'.self::LABEL.'}'],
            ['slug' => 'step', 'label' => 'New step', 'fields' => [['name' => self::LABEL, 'required' => true]], 'item_label' => '{'.self::LABEL.'}'],
        ];
    }
}
