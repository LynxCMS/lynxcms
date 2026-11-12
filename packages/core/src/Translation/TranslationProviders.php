<?php

declare(strict_types=1);

namespace LynxCms\Core\Translation;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Translation\TranslationProvider;

/**
 * The machine translation providers a site can use, by name (ADR-0100). Packages register theirs
 * at boot; pages.translate picks one by name, or the default: the container's TranslationProvider
 * binding, else the first one registered. Extensions wrap every provider handed out, so a module
 * can meter calls or add options such as a glossary to all of them (ADR-0104).
 *
 * @api
 */
final class TranslationProviders
{
    /** @var array<string, Closure(): TranslationProvider> */
    private array $factories = [];

    /** @var list<Closure(TranslationProvider): TranslationProvider> */
    private array $extensions = [];

    public function __construct(private readonly Container $container) {}

    /** @param  Closure(): TranslationProvider  $factory  called each time the provider is used */
    public function register(string $name, Closure $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * The registered providers' names, the default's first.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $default = $this->hasDefault() ? $this->resolve(null)->name() : null;

        return array_values(array_unique(array_filter([$default, ...array_keys($this->factories)], is_string(...))));
    }

    public function hasDefault(): bool
    {
        return $this->container->bound(TranslationProvider::class) || $this->factories !== [];
    }

    /**
     * Wraps every provider handed out from now on, in the order added.
     *
     * @param  Closure(TranslationProvider): TranslationProvider  $extension
     */
    public function extend(Closure $extension): void
    {
        $this->extensions[] = $extension;
    }

    /** The provider by name, or the default with none; refuses a name nobody registered. */
    public function get(?string $name = null): TranslationProvider
    {
        $provider = $this->resolve($name);

        foreach ($this->extensions as $extension) {
            $provider = $extension($provider);
        }

        return $provider;
    }

    private function resolve(?string $name): TranslationProvider
    {
        if ($name === null) {
            return $this->default();
        }

        if (isset($this->factories[$name])) {
            return ($this->factories[$name])();
        }

        $default = $this->hasDefault() ? $this->default() : null;

        return $default?->name() === $name
            ? $default
            : throw ValidationException::withMessages(['provider' => "No translation provider is named [{$name}]."]);
    }

    private function default(): TranslationProvider
    {
        $bound = $this->container->bound(TranslationProvider::class) ? $this->container->make(TranslationProvider::class) : null;

        if ($bound instanceof TranslationProvider) {
            return $bound;
        }

        $first = array_key_first($this->factories)
            ?? throw ValidationException::withMessages(['provider' => 'No translation provider is configured. Install lynxcms/ai or a translation module.']);

        return ($this->factories[$first])();
    }
}
