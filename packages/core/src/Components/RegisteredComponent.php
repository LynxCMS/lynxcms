<?php

declare(strict_types=1);

namespace LynxCms\Core\Components;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use LynxCms\Core\Support\Value;

/**
 * An app component that editors and AI agents can place on pages through a "component" field.
 *
 * @api
 */
final readonly class RegisteredComponent
{
    /**
     * @param  class-string|string  $component  Livewire class or component name
     * @param  (Closure(JsonSchema): array<string, Type>)|null  $settings
     * @param  array<string, mixed>  $rules
     */
    public function __construct(
        public string $alias,
        public string $component,
        public string $description = '',
        public ?Closure $settings = null,
        public array $rules = [],
    ) {}

    /**
     * @return array<string, Type>
     */
    public function settingsSchema(JsonSchema $schema): array
    {
        return $this->settings === null ? [] : ($this->settings)($schema);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function validateSettings(array $settings): array
    {
        return $this->rules === [] ? $settings : Value::map(Validator::make($settings, $this->rules)->validate() + $settings);
    }
}
