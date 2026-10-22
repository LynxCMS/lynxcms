<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * The subfields a repeater's items have, or those of any of a matrix's types: what a list
 * property's sub-properties can read.
 *
 * @internal
 */
final class ListSubfields
{
    /** @return list<Field> */
    public static function of(?Field $list): array
    {
        $settings = $list?->config() ?? [];
        $groups = [Value::list($settings['fields'] ?? []), ...array_map(fn (mixed $type): array => Value::list(Value::map($type)['fields'] ?? []), Value::list($settings['types'] ?? []))];
        $fields = [];

        foreach (array_merge(...$groups) as $subfield) {
            $field = lynx()->fields->find(Value::string(Value::map($subfield)['name'] ?? ''));

            if ($field !== null) {
                $fields[$field->name] = $field;
            }
        }

        return array_values($fields);
    }
}
