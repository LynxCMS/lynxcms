<?php

declare(strict_types=1);

namespace LynxCms\Core\PhpStan;

use LynxCms\Core\Pages\Items\Item;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use PHPStan\Analyser\DeclarationDependencyTracker;
use PHPStan\DependencyInjection\Container;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use RuntimeException;

/**
 * PHPStan extension (ADR-0083): $page->field and $item->field have the types lynx:phpstan wrote
 * from the schema, resolved in PHPStan's own container, so they may name any class the app can
 * load. Only loaded by PHPStan, through phpstan/extension.neon. The types file is declared as what
 * Page and Item depend on, so PHPStan 2.3 and later analyse again when lynx:phpstan rewrites it;
 * older versions keep their result cache until it is cleared.
 *
 * @internal
 */
final class FieldProperties implements PropertiesClassReflectionExtension
{
    /** @var array{page: array<string, array{type: string, writable: bool}>, item: array<string, array{type: string, writable: bool}>}|null */
    private ?array $types = null;

    /** @var array<string, FieldProperty> */
    private array $properties = [];

    private readonly ?DeclarationDependencyTracker $dependencies;

    public function __construct(private readonly string $typesFile, private readonly TypeStringResolver $resolver, Container $container)
    {
        $this->dependencies = interface_exists(DeclarationDependencyTracker::class) ? $container->getByType(DeclarationDependencyTracker::class) : null;
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        $kind = $this->kind($classReflection);

        if ($kind === null) {
            return false;
        }

        $this->dependencies?->trackFileDependency($classReflection, $this->typesFile);

        return isset($this->types()[$kind][$propertyName]);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $kind = $this->kind($classReflection) ?? throw new RuntimeException("Lynx types no properties of [{$classReflection->getName()}].");
        $property = $this->types()[$kind][$propertyName] ?? throw new RuntimeException("Lynx has no field [{$propertyName}]; run php artisan lynx:phpstan.");

        return $this->properties["{$kind}.{$propertyName}"] ??= new FieldProperty($classReflection, $this->resolver->resolve($property['type']), $property['writable']);
    }

    /** "page" or "item" for the classes whose magic properties are fields, else null. */
    private function kind(ClassReflection $classReflection): ?string
    {
        return match ($classReflection->getName()) {
            Page::class => 'page',
            Item::class => 'item',
            default => null,
        };
    }

    /**
     * @return array{page: array<string, array{type: string, writable: bool}>, item: array<string, array{type: string, writable: bool}>}
     */
    private function types(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        if (! is_file($this->typesFile)) {
            throw new RuntimeException("Lynx's field types are missing at [{$this->typesFile}]: run php artisan lynx:phpstan.");
        }

        $types = Value::map(require $this->typesFile);

        return $this->types = ['page' => $this->properties($types['page'] ?? []), 'item' => $this->properties($types['item'] ?? [])];
    }

    /**
     * @return array<string, array{type: string, writable: bool}>
     */
    private function properties(mixed $list): array
    {
        $properties = [];

        foreach (Value::map($list) as $name => $property) {
            $property = Value::map($property);
            $properties[$name] = ['type' => Value::string($property['type'] ?? null, 'mixed'), 'writable' => Value::bool($property['writable'] ?? false)];
        }

        return $properties;
    }
}
