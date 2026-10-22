<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;

/**
 * Read access to templates and their fields, cached per request.
 *
 * @api
 */
final class TemplateRegistry implements Flushable
{
    /** @var Collection<string, Template>|null */
    private ?Collection $templates = null;

    /** @var array<int, Collection<string, TemplateField>> */
    private array $fields = [];

    public function __construct(private readonly FieldRegistry $fieldRegistry) {}

    /**
     * @return Collection<string, Template>
     */
    public function all(): Collection
    {
        return $this->templates ??= Template::query()->with('fields')->orderBy('name')->get()->keyBy('name');
    }

    public function find(Template|int|string|null $reference): ?Template
    {
        return match (true) {
            $reference === null => null,
            $reference instanceof Template => $this->all()->get($reference->name),
            is_int($reference) => $this->all()->first(fn (Template $t): bool => $t->id === $reference),
            default => $this->all()->get($reference) ?? $this->all()->first(fn (Template $t): bool => $t->uuid === $reference),
        };
    }

    public function get(Template|int|string $reference): Template
    {
        return $this->find($reference) ?? throw new InvalidArgumentException('Template ['.(is_scalar($reference) ? $reference : $reference->name).'] does not exist.');
    }

    /**
     * The template's fields in order, keyed by field name.
     *
     * @return Collection<string, TemplateField>
     */
    public function fields(Template|int|string $template): Collection
    {
        $template = $this->get($template);

        return $this->fields[$template->id] ??= $template->fields->mapWithKeys(function (Field $field): array {
            $pivot = $field->getRelation('pivot');
            $settings = $pivot instanceof Model ? $pivot->getAttribute('settings') : null;

            return [$field->name => new TemplateField(
                $this->fieldRegistry->get($field->name),
                $pivot instanceof Model ? Value::int($pivot->getAttribute('sort')) : 0,
                Value::map(is_string($settings) ? json_decode($settings, true) : $settings),
            )];
        });
    }

    public function flush(): void
    {
        $this->templates = null;
        $this->fields = [];
    }
}
