<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Moves a subfield's values out of the item rows of a repeater or matrix and into one language's
 * rows (ADR-0044), for a subfield that has become translatable (ADR-0118). Every item, switched
 * off or not, and every page's draft keeps its value, now as that language's.
 *
 * @internal
 */
final class SubfieldLanguageRows
{
    /**
     * @return list<int> the pages whose items changed
     */
    public function moveToLanguage(Field $itemField, string $subfield, int $languageId): array
    {
        $table = $itemField->table();
        $pages = [];

        foreach (DB::table($table)->where('language_id', ItemTable::ITEM_ROW)->orderBy('page_id')->orderBy('item')->lazy() as $row) {
            $data = Value::map(json_decode(Value::string($row->data, '{}'), true) ?? []);

            if (! array_key_exists($subfield, $data)) {
                continue;
            }

            $value = $data[$subfield];
            unset($data[$subfield]);
            $key = ['page_id' => $row->page_id, 'item' => $row->item];
            DB::table($table)->where($key + ['language_id' => ItemTable::ITEM_ROW])->update(['data' => $this->json($data)]);

            if ($value !== null) {
                $this->write($table, $key + ['language_id' => $languageId], $subfield, $value);
            }

            $pages[] = Value::int($row->page_id);
        }

        return array_values(array_unique($pages));
    }

    /** @param  array<string, mixed>  $key */
    private function write(string $table, array $key, string $subfield, mixed $value): void
    {
        $row = DB::table($table)->where($key)->first();

        if ($row === null) {
            DB::table($table)->insert($key + ['data' => $this->json([$subfield => $value])]);

            return;
        }

        $data = Value::map(json_decode(Value::string($row->data, '{}'), true) ?? []);
        DB::table($table)->where($key)->update(['data' => $this->json([...$data, $subfield => $value])]);
    }

    /** @param  array<string, mixed>  $values */
    private function json(array $values): string
    {
        return json_encode((object) $values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
