<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use ArrayAccess;
use Illuminate\Support\Arr;
use LogicException;
use LynxCms\Core\Support\Value;

/**
 * Validated input of an action: an associative array with typed accessors.
 *
 * @implements ArrayAccess<string, mixed>
 *
 * @api
 */
final readonly class Input implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function string(string $key, ?string $default = null): ?string
    {
        return Value::nullableString($this->get($key, $default));
    }

    public function int(string $key, ?int $default = null): ?int
    {
        return Value::nullableInt($this->get($key, $default));
    }

    public function bool(string $key, bool $default = false): bool
    {
        return Value::bool($this->get($key), $default);
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key): array
    {
        return Value::array($this->get($key, []));
    }

    public function with(string $key, mixed $value): self
    {
        $data = $this->data;
        Arr::set($data, $key, $value);

        return new self(Value::map($data));
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Action input is immutable; use with().');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Action input is immutable.');
    }
}
