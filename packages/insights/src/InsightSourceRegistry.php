<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The insight sources a report runs, added by modules and apps from their service providers.
 *
 * @api
 */
final class InsightSourceRegistry
{
    /** @var array<string, InsightSource|class-string<InsightSource>> */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Add sources, as instances or class names resolved when a report runs. A class name is keyed
     * by itself until then, so adding it twice keeps one.
     *
     * @param  InsightSource|class-string<InsightSource>  ...$sources
     */
    public function add(InsightSource|string ...$sources): void
    {
        foreach ($sources as $source) {
            $this->sources[is_string($source) ? $source : $source->key()] = $source;
        }
    }

    /** @return list<InsightSource> */
    public function all(): array
    {
        return array_values(array_map($this->resolve(...), $this->sources));
    }

    /**
     * @param  InsightSource|class-string<InsightSource>  $source
     */
    private function resolve(InsightSource|string $source): InsightSource
    {
        $resolved = is_string($source) ? $this->container->make($source) : $source;

        if (! $resolved instanceof InsightSource) {
            throw new InvalidArgumentException('An insight source must implement '.InsightSource::class.'.');
        }

        return $resolved;
    }
}
