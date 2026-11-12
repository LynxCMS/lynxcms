<?php

declare(strict_types=1);

use Illuminate\Container\Container;

/*
 * Scoped services (the current language, loaded pages, resolved paths, permissions) live for one
 * request: Octane, the queue worker, the page cache's warmer and the static export start each
 * request with new ones. A singleton lives longer, so it must not keep one from its constructor,
 * or every later request reads the first one's. It resolves scoped services when it uses them.
 */

/**
 * The scoped services a class keeps through its constructor, also through the classes it builds
 * there, which live as long as it does. Singletons are checked on their own.
 *
 * @param  list<string>  $scoped
 * @param  list<string>  $seen
 * @return list<string>
 */
function keptScopedServices(string $class, array $scoped, array $seen = []): array
{
    if (! class_exists($class) || in_array($class, $seen, true)) {
        return [];
    }

    $kept = [];

    foreach (new ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
        $type = $parameter->getType();
        $dependency = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

        if ($dependency === null || app()->isShared($dependency) && ! in_array($dependency, $scoped, true)) {
            continue;
        }

        $path = implode(' > ', [...$seen, $class]);
        $kept = in_array($dependency, $scoped, true)
            ? [...$kept, "{$path} keeps {$dependency}"]
            : [...$kept, ...keptScopedServices($dependency, $scoped, [...$seen, $class])];
    }

    return $kept;
}

/** @return list<string> */
function scopedServices(): array
{
    return array_values(new ReflectionProperty(Container::class, 'scopedInstances')->getValue(app()));
}

it('keeps no scoped service in a Lynx singleton, which outlives a request', function (): void {
    $scoped = scopedServices();
    $singletons = array_filter(
        array_keys(app()->getBindings()),
        fn (string $abstract): bool => str_starts_with($abstract, 'LynxCms\\') && app()->isShared($abstract) && ! in_array($abstract, $scoped, true),
    );

    expect(array_merge(...array_map(fn (string $class): array => keptScopedServices($class, $scoped), array_values($singletons))))->toBe([]);
});
