<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LynxCms\Contracts\Fields\FieldType;

/**
 * Registered field types. Modules add their own with Lynx::fieldTypes()->register(MyType::class).
 *
 * @api
 */
final class FieldTypeRegistry
{
    /** @var array<string, class-string<FieldType>> */
    private array $types = [];

    /** @var array<string, FieldType> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<FieldType>  ...$types
     */
    public function register(string ...$types): void
    {
        foreach ($types as $type) {
            if (! is_subclass_of($type, FieldType::class)) {
                throw new InvalidArgumentException("[{$type}] is not a Lynx field type.");
            }

            $this->types[$type::name()] = $type;
            unset($this->instances[$type::name()]);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): FieldType
    {
        if (! isset($this->types[$name])) {
            throw new InvalidArgumentException("Unknown field type [{$name}].");
        }

        if (! isset($this->instances[$name])) {
            $type = $this->container->make($this->types[$name]);

            if (! $type instanceof FieldType) {
                throw new InvalidArgumentException("[{$this->types[$name]}] did not resolve to a field type.");
            }

            $this->instances[$name] = $type;
        }

        return $this->instances[$name];
    }

    /**
     * @return array<string, class-string<FieldType>>
     */
    public function all(): array
    {
        ksort($this->types);

        return $this->types;
    }
}
