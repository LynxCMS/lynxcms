<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use stdClass;

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

    /** @var array<int, list<stdClass>>|null every template's field rows, by template id, in order */
    private ?array $rows = null;

    public function __construct(private readonly FieldRegistry $fieldRegistry) {}

    /**
     * @return Collection<string, Template>
     */
    public function all(): Collection
    {
        return $this->templates ??= Template::query()->orderBy('name')->get()->keyBy('name');
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

        return $this->fields[$template->id] ??= $this->fieldsOf($template->id);
    }

    /**
     * Built from the template's rows and FieldRegistry's models, without the template's fields
     * relation, so no field is hydrated twice.
     *
     * @return Collection<string, TemplateField>
     */
    private function fieldsOf(int $templateId): Collection
    {
        $byId = $this->fieldRegistry->all()->keyBy('id');
        $fields = new Collection;

        foreach ($this->rows()[$templateId] ?? [] as $row) {
            $field = $byId->get(Value::int($row->field_id));
            $settings = $row->settings;

            if ($field instanceof Field) {
                $fields->put($field->name, new TemplateField($field, Value::int($row->sort), Value::map(is_string($settings) ? json_decode($settings, true) : $settings)));
            }
        }

        return $fields;
    }

    /**
     * Every template's field rows in one query, however many templates the request reads.
     *
     * @return array<int, list<stdClass>>
     */
    private function rows(): array
    {
        if ($this->rows === null) {
            $this->rows = [];

            foreach (DB::table(Tables::TEMPLATE_FIELDS)->orderBy('sort')->get() as $row) {
                $this->rows[Value::int($row->template_id)][] = $row;
            }
        }

        return $this->rows;
    }

    public function flush(): void
    {
        $this->templates = null;
        $this->fields = [];
        $this->rows = null;
    }
}
