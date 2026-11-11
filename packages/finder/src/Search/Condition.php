<?php

declare(strict_types=1);

namespace LynxCms\Finder\Search;

use LynxCms\Core\Support\Value;

/**
 * One row of the finder: what it looks at (a field name, or a page property such as "template"),
 * how it compares (an operator key) and the value, or the pages chosen for a page reference.
 * "to" is the second value of "between".
 *
 * @api
 */
final readonly class Condition
{
    /**
     * @param  list<int>  $pages
     */
    public function __construct(
        public string $subject,
        public string $operator,
        public string $value = '',
        public string $to = '',
        public array $pages = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $pages = array_values(array_filter(Value::ints($data['pages'] ?? []), fn (int $id): bool => $id > 0));

        return new self(
            trim(Value::string($data['subject'] ?? '')),
            trim(Value::string($data['operator'] ?? '')),
            trim(Value::string($data['value'] ?? '')),
            trim(Value::string($data['to'] ?? '')),
            $pages,
        );
    }

    /**
     * @return array{subject: string, operator: string, value: string, to: string, pages: list<int>}
     */
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'operator' => $this->operator, 'value' => $this->value, 'to' => $this->to, 'pages' => $this->pages];
    }
}
