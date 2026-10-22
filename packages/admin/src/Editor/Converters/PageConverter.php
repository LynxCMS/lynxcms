<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PageChoices;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Page references: a list of ids in the form, or one id (a string, empty for none) when a select or
 * radios choose one page; [id, title] pairs, loaded in one batch, for display.
 *
 * @internal
 */
final class PageConverter implements FormConverter, PreparesDisplay
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        $ids = Value::ints(is_array($value) ? $value : ($value === null ? [] : [$value]));

        if ($field instanceof Field && PageChoices::holdsOne($field->config())) {
            return isset($ids[0]) ? (string) $ids[0] : '';
        }

        return $ids;
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $ids = Value::ints(is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]));

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    public function display(mixed $value): mixed
    {
        $ids = array_values(array_filter(Value::ints(is_array($value) ? $value : [$value]), fn (int $id): bool => $id > 0));
        $found = $ids === [] ? [] : lynx()->pages->find('id='.implode('|', $ids).', include=all')->map(fn (Page $page): Page => $page);
        $titles = [];

        foreach ($found as $page) {
            $titles[(int) $page->id] = (new DefaultPresenter)->title($page);
        }

        return array_map(fn (int $id): array => ['id' => $id, 'title' => $titles[$id] ?? null], $ids);
    }
}
