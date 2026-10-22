<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\CarriesFiles;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\MergesInput;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Items\ItemList;
use LynxCms\Core\Query\JsonSql;
use LynxCms\Core\Search\ItemSearchText;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * A field whose value is a list of items, each with values of other fields (ADR-0044). Subclasses
 * say which fields an item shows: a repeater always the same ones, a matrix those of the item's type.
 *
 * @phpstan-import-type ItemValue from ItemRows
 *
 * @api
 */
abstract class ItemFieldType extends BaseFieldType implements CarriesFiles, DescribesPhpType, MergesInput, PresentsValue, ProvidesSearchText, ReferencesPages, StoresItems
{
    public function __construct(
        protected readonly FieldRegistry $fields,
        private readonly ItemRows $rows,
        private readonly ItemMerger $merger,
    ) {}

    /**
     * The fields an item of a type shows (the type is null for repeaters).
     *
     * @param  array<string, mixed>  $settings
     * @return list<Subfield>
     */
    abstract public function subfields(array $settings, ?string $type): array;

    /**
     * The item's type, checked; null for repeaters. Report problems with $normalizer->error().
     *
     * @param  array<string, mixed>  $settings
     */
    abstract protected function itemType(mixed $type, int $index, array $settings, ItemNormalizer $normalizer): ?string;

    public function supportsTranslation(): bool
    {
        return false;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        // Item fields get their own table shape (ItemTable).
    }

    public function indexable(): bool
    {
        return false;
    }

    public function comparison(): Comparison
    {
        return Comparison::None;
    }

    public function rules(array $settings): array
    {
        return ['array'];
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === [] || $value === null;
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $normalizer = app(ItemNormalizer::class);

        return $normalizer->normalize(
            $value,
            $settings,
            fn (?string $type): array => $this->subfields($settings, $type),
            fn (mixed $type, int $index): ?string => $this->itemType($type, $index, $settings, $normalizer),
        );
    }

    public function sleep(mixed $value, array $settings): array
    {
        /** @var list<ItemValue> $value */
        return $this->rows->sleep($value, $this->isTranslatedKey(...));
    }

    /**
     * @param  list<stdClass>  $rows
     */
    public function wake(array $rows, array $settings): mixed
    {
        return $this->rows->wake($rows);
    }

    public function merge(mixed $stored, mixed $input, string $languageCode, array $settings): mixed
    {
        return $this->merger->merge($stored, $input, $languageCode, $this->isTranslatedKey(...));
    }

    public function present(mixed $value, Field $field): mixed
    {
        return new ItemList(Value::list($value), $field, $this);
    }

    public function pageIds(mixed $value, array $settings): array
    {
        $ids = [];

        foreach ($this->referenceValues($value) as [$type, $subValue, $config]) {
            array_push($ids, ...$type->pageIds($subValue, $config));
        }

        return $ids;
    }

    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed
    {
        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);
            $values = Value::map($item['values'] ?? []);

            foreach ($values as $name => $subValue) {
                $field = $this->fields->find($name);
                $type = $field?->fieldType();

                if ($field !== null && $type instanceof ReferencesPages) {
                    $values[$name] = $field->isTranslatable()
                        ? array_map(fn (mixed $v): mixed => $type->mapPageIds($v, $map, $field->config()), Value::map($subValue))
                        : $type->mapPageIds($subValue, $map, $field->config());
                }
            }

            $items[] = ['values' => $values] + $item;
        }

        return $items;
    }

    public function searchText(mixed $value, array $settings, Language $language): string
    {
        return app(ItemSearchText::class)->of($this, $value, $settings, $language);
    }

    public function files(mixed $value, array $settings): array
    {
        return app(ItemFiles::class)->files($value);
    }

    public function mapFiles(mixed $value, Closure $map, array $settings): mixed
    {
        return app(ItemFiles::class)->mapFiles($value, $map);
    }

    public function whereReferences(Builder $query, int $pageId, array $settings): void
    {
        $names = $this->referenceFieldNames($settings);

        $query->where('language_id', ItemTable::ITEM_ROW)->where(function (Builder $q) use ($names, $pageId): void {
            $q->whereRaw('1 = 0');

            foreach ($names as $name) {
                JsonSql::contains($q, 'data', $name, $pageId, 'or');
            }
        });
    }

    /**
     * Subfield entries ("name" or {"name", "label", ...}) as subfields; unknown fields are skipped.
     *
     * @param  list<mixed>  $entries
     * @return list<Subfield>
     */
    protected function subfieldsOf(array $entries): array
    {
        $subfields = [];

        foreach ($entries as $entry) {
            $entry = is_string($entry) ? ['name' => $entry] : Value::map($entry);
            $field = $this->fields->find(Value::string($entry['name'] ?? ''));

            if ($field instanceof Field) {
                $subfields[] = new Subfield($field, $entry);
            }
        }

        return $subfields;
    }

    /** Whether values under this key hold one value per language (translatable fields, by default). */
    protected function isTranslatedKey(string $name): bool
    {
        return $this->fields->find($name)?->isTranslatable() === true;
    }

    /**
     * Every field an item may show, whatever its type.
     *
     * @param  array<string, mixed>  $settings
     * @return list<Subfield>
     */
    public function allSubfields(array $settings): array
    {
        return $this->subfields($settings, null);
    }

    /**
     * Names of the subfields whose fields reference pages.
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function referenceFieldNames(array $settings): array
    {
        $names = [];

        foreach ($this->allSubfields($settings) as $subfield) {
            if ($subfield->type() instanceof ReferencesPages) {
                $names[] = $subfield->name();
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Every referencing subfield value in a stored value: [type, value, field settings].
     *
     * @return list<array{0: ReferencesPages, 1: mixed, 2: array<string, mixed>}>
     */
    private function referenceValues(mixed $value): array
    {
        $found = [];

        foreach (Value::list($value) as $item) {
            foreach (Value::map(Value::array($item)['values'] ?? []) as $name => $subValue) {
                $field = $this->fields->find($name);
                $type = $field?->fieldType();

                if ($field === null || ! $type instanceof ReferencesPages) {
                    continue;
                }

                foreach ($field->isTranslatable() ? Value::map($subValue) : [$subValue] as $one) {
                    $found[] = [$type, $one, $field->config()];
                }
            }
        }

        return $found;
    }

    public function phpType(Field $field): string
    {
        return '\\'.ItemList::class;
    }
}
