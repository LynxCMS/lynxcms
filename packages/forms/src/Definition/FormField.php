<?php

declare(strict_types=1);

namespace LynxCms\Forms\Definition;

use Closure;
use InvalidArgumentException;
use LynxCms\Core\Support\LocalizedText;

/**
 * A field of a code form (ADR-0052), built fluently:
 * FormField::text('name')->label(['pt' => 'Nome', 'en' => 'Name'])->required()->max(120).
 * Texts are a string, or a map of language codes to text. Each modifier returns a copy.
 *
 * @api
 */
final class FormField
{
    public const array TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'number', 'date', 'file', 'group'];

    /** Types whose value is one of the options, or a list of them. */
    public const array CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    public LocalizedText $label;

    public ?LocalizedText $help = null;

    public bool $isRequired = false;

    public bool $isReplyTo = false;

    public ?int $max = null;

    public int $width = 100;

    /** @var array<string, LocalizedText> option value => label */
    public array $options = [];

    /** @var list<mixed> extra Laravel rules */
    public array $rules = [];

    /** @var array<string, LocalizedText> rule => message */
    public array $messages = [];

    /** Comparisons showWhen() takes, and how isShown() applies them. */
    public const array OPERATORS = ['=' => 'in', '!=' => 'not_in', '<' => '<', '<=' => '<=', '>' => '>', '>=' => '>='];

    /** @var array{field: string, operator: string, values: list<string>}|null */
    public ?array $showIf = null;

    /** File types a file question takes when it names none: what staff can open safely. */
    public const array DEFAULT_EXTENSIONS = [
        'pdf', 'txt', 'rtf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'zip',
    ];

    /** Lets extensions() take any file type. */
    public const string ANY_EXTENSION = '*';

    /** @var list<string> */
    public array $extensions = [];

    public int $maxKb = 10240;

    public int $maxFiles = 1;

    /** @var list<FormField> a group's fields */
    public array $fields = [];

    public int $maxRows = 10;

    private function __construct(public readonly string $name, public readonly string $type)
    {
        $this->label = LocalizedText::from($name);
    }

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function email(string $name): self
    {
        return new self($name, 'email');
    }

    public static function tel(string $name): self
    {
        return new self($name, 'tel');
    }

    public static function textarea(string $name): self
    {
        return new self($name, 'textarea');
    }

    public static function select(string $name): self
    {
        return new self($name, 'select');
    }

    public static function radio(string $name): self
    {
        return new self($name, 'radio');
    }

    public static function checkboxes(string $name): self
    {
        return new self($name, 'checkboxes');
    }

    /** A single box, such as consent: required() means it must be ticked. */
    public static function checkbox(string $name): self
    {
        return new self($name, 'checkbox');
    }

    public static function number(string $name): self
    {
        return new self($name, 'number');
    }

    public static function date(string $name): self
    {
        return new self($name, 'date');
    }

    public static function file(string $name): self
    {
        return new self($name, 'file');
    }

    /** Rows of the given fields that visitors add and remove, such as deliverables. */
    public static function group(string $name): self
    {
        return new self($name, 'group');
    }

    /** @param string|array<string, string> $label */
    public function label(string|array $label): self
    {
        return $this->with(fn (self $f): LocalizedText => $f->label = LocalizedText::from($label));
    }

    /** @param string|array<string, string> $help */
    public function help(string|array $help): self
    {
        return $this->with(fn (self $f): LocalizedText => $f->help = LocalizedText::from($help));
    }

    public function required(bool $required = true): self
    {
        return $this->with(fn (self $f): bool => $f->isRequired = $required);
    }

    /** Characters for text, the largest value for numbers. */
    public function max(int $max): self
    {
        return $this->with(fn (self $f): int => $f->max = $max);
    }

    /** Percent of the row the input takes: 100, 50, 33 or 25. */
    public function width(int $width): self
    {
        return $this->with(fn (self $f): int => $f->width = $width);
    }

    /** @param array<string, string|array<string, string>> $options value => label */
    public function options(array $options): self
    {
        return $this->with(fn (self $f): array => $f->options = array_map(LocalizedText::from(...), $options));
    }

    /** @param list<mixed> $rules extra Laravel validation rules */
    public function rules(array $rules): self
    {
        return $this->with(fn (self $f): array => $f->rules = $rules);
    }

    /** @param array<string, string|array<string, string>> $messages rule => message, e.g. ['accepted' => [...]] */
    public function messages(array $messages): self
    {
        return $this->with(fn (self $f): array => $f->messages = array_map(LocalizedText::from(...), $messages));
    }

    /** Asked only when another field has one of these values (ADR-0030 style); "1" and "0" for a tick box. */
    public function showIf(string $field, string ...$values): self
    {
        return $this->with(fn (self $f): array => $f->showIf = ['field' => $field, 'operator' => 'in', 'values' => array_values($values)]);
    }

    /**
     * Asked only when another field compares with a value: showWhen('guests', '>=', 50),
     * showWhen('subject', '!=', 'press'), showWhen('newsletter', '=', true) for a ticked box.
     */
    public function showWhen(string $field, string $operator, int|float|string|bool $value): self
    {
        $applied = self::OPERATORS[$operator] ?? throw new InvalidArgumentException("[{$operator}] is not a comparison: use ".implode(', ', array_keys(self::OPERATORS)).'.');
        $text = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return $this->with(fn (self $f): array => $f->showIf = ['field' => $field, 'operator' => $applied, 'values' => [$text]]);
    }

    /**
     * The file types a file question takes; without a call, DEFAULT_EXTENSIONS, and ['*'] for any.
     *
     * @param  list<string>  $extensions
     */
    public function extensions(array $extensions): self
    {
        return $this->with(fn (self $f): array => $f->extensions = array_map(strtolower(...), $extensions));
    }

    /** @return list<string>|null the extensions a file question takes, or null for any */
    public function allowedExtensions(): ?array
    {
        return match (true) {
            in_array(self::ANY_EXTENSION, $this->extensions, true) => null,
            $this->extensions === [] => self::DEFAULT_EXTENSIONS,
            default => $this->extensions,
        };
    }

    public function maxKb(int $kilobytes): self
    {
        return $this->with(fn (self $f): int => $f->maxKb = $kilobytes);
    }

    public function maxFiles(int $files): self
    {
        return $this->with(fn (self $f): int => $f->maxFiles = $files);
    }

    /** @param list<FormField> $fields */
    public function fields(array $fields): self
    {
        return $this->with(fn (self $f): array => $f->fields = $fields);
    }

    public function maxRows(int $rows): self
    {
        return $this->with(fn (self $f): int => $f->maxRows = $rows);
    }

    /** The submitter's address: answers and the confirmation go to it. */
    public function replyTo(): self
    {
        return $this->with(fn (self $f): true => $f->isReplyTo = true);
    }

    /** Whether the answers so far show this field. */
    public function isShown(array $data): bool
    {
        if ($this->showIf === null) {
            return true;
        }

        $value = $data[$this->showIf['field']] ?? null;
        $values = $this->showIf['values'];
        // An answer not given yet counts as an unticked box ("0"), and matches nothing else.
        $isEmpty = in_array($value, [null, '', false, []], true);
        $answers = array_map(fn (mixed $answer): string => is_bool($answer) ? ($answer ? '1' : '0') : (is_scalar($answer) ? (string) $answer : ''), is_array($value) ? $value : [$value]);

        return match ($this->showIf['operator']) {
            'in' => $isEmpty ? in_array('0', $values, true) : array_intersect($answers, $values) !== [],
            'not_in' => ! $isEmpty && array_intersect($answers, $values) === [],
            default => is_numeric($value) && is_numeric($values[0] ?? null) && $this->compares((float) $value, $this->showIf['operator'], (float) $values[0]),
        };
    }

    private function compares(float $answer, string $operator, float $value): bool
    {
        return match ($operator) {
            '<' => $answer < $value,
            '<=' => $answer <= $value,
            '>' => $answer > $value,
            default => $answer >= $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label->toStorage(),
            'options' => array_map(fn (LocalizedText $label): string|array => $label->toStorage(), $this->options),
            'fields' => array_map(fn (self $field): array => $field->snapshot(), $this->fields),
        ], fn (mixed $value): bool => $value !== []);
    }

    /** @param Closure(self): mixed $change */
    private function with(Closure $change): self
    {
        $copy = clone $this;
        $change($copy);

        return $copy;
    }
}
