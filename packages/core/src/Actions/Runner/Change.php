<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

/**
 * One change an action made (or would make, in a dry run).
 *
 * @api
 */
final readonly class Change
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public const string SCHEMA = 'schema';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $type,
        public string $subject,
        public int|string|null $id,
        public string $summary,
        public array $data = [],
        public bool $destructive = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'subject' => $this->subject,
            'id' => $this->id,
            'summary' => $this->summary,
            'data' => $this->data,
            'destructive' => $this->destructive,
        ];
    }
}
