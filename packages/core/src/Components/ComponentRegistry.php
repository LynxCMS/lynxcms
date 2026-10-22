<?php

declare(strict_types=1);

namespace LynxCms\Core\Components;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * App components available to the "component" field type (ADR-0021).
 *
 *   Lynx::components()->register('enrol-form', EnrolForm::class,
 *       settings: fn (JsonSchema $s) => ['notify_email' => $s->string()->required()],
 *       rules: ['notify_email' => ['required', 'email']]);
 *
 * @api
 */
final class ComponentRegistry
{
    /** @var array<string, RegisteredComponent> */
    private array $components = [];

    /**
     * Settings are shared by every language (ADR-0021), and editors change them in the page
     * editor, so they should hold configuration (a capacity, a layout, an id). Text visitors read
     * belongs in the component's view through Laravel translations or in translatable page fields.
     *
     * @param  (Closure(JsonSchema): array<string, Type>)|null  $settings
     * @param  array<string, mixed>  $rules
     */
    public function register(string $alias, string $component, string $description = '', ?Closure $settings = null, array $rules = []): RegisteredComponent
    {
        return $this->components[$alias] = new RegisteredComponent($alias, $component, $description, $settings, $rules);
    }

    public function find(string $alias): ?RegisteredComponent
    {
        return $this->components[$alias] ?? null;
    }

    /**
     * @return array<string, RegisteredComponent>
     */
    public function all(): array
    {
        return $this->components;
    }
}
