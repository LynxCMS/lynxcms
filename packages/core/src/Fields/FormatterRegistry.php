<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LynxCms\Contracts\Fields\Formatter;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;

/**
 * Registered text formatters (ADR-0031). Modules add their own with
 * lynx()->formatters()->register(MyFormatter::class).
 *
 * @api
 */
final class FormatterRegistry
{
    /** @var array<string, class-string<Formatter>> */
    private array $formatters = [];

    /** @var array<string, Formatter> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Formatter>  ...$formatters
     */
    public function register(string ...$formatters): void
    {
        foreach ($formatters as $formatter) {
            if (! is_subclass_of($formatter, Formatter::class)) {
                throw new InvalidArgumentException("[{$formatter}] is not a Lynx formatter.");
            }

            $this->formatters[$formatter::name()] = $formatter;
            unset($this->instances[$formatter::name()]);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    public function get(string $name): Formatter
    {
        if (! isset($this->formatters[$name])) {
            throw new InvalidArgumentException("Unknown formatter [{$name}].");
        }

        if (! isset($this->instances[$name])) {
            $instance = $this->container->make($this->formatters[$name]);
            $this->instances[$name] = $instance instanceof Formatter ? $instance : throw new InvalidArgumentException("[{$name}] did not resolve to a formatter.");
        }

        return $this->instances[$name];
    }

    /**
     * Formatters that apply to a field type, in registration order.
     *
     * @return array<string, string> name => description
     */
    public function forType(string $fieldType): array
    {
        $result = [];

        foreach ($this->formatters as $name => $class) {
            if ($this->get($name)->appliesTo($fieldType)) {
                $result[$name] = $class::description();
            }
        }

        return $result;
    }

    /**
     * A value of $field as a template prints it: the field's format, then its formatters (ADR-0031).
     * For previews, e.g. in the admin's editor.
     */
    public function render(Field $field, mixed $value, Language|string|null $language = null): string
    {
        $language = $language === null ? null : app(LanguageRegistry::class)->get($language);

        return $this->container->make(FormatterChain::class)->render($field, $value, $language);
    }
}
