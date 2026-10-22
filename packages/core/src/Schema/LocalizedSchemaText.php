<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Keeps schema text keyed by language code (ADR-0043) pointing at the right language when a
 * language's code changes: field and template labels, and the label, description, notes and add
 * label in field settings and template field settings, at any depth (repeater and matrix fields
 * nest them).
 *
 * @internal
 */
final class LocalizedSchemaText
{
    private const array KEYS = ['label', 'description', 'notes', 'add_label'];

    public function renameLanguage(string $from, string $to): void
    {
        foreach (Field::query()->get() as $field) {
            $field->forceFill([
                'label' => $field->labels()->renameLanguage($from, $to)->toColumn(),
                'settings' => $field->settings === null ? null : $this->rename($field->settings, $from, $to),
            ])->save();
        }

        foreach (Template::query()->get() as $template) {
            $template->forceFill(['label' => $template->labels()->renameLanguage($from, $to)->toColumn()])->save();
        }

        foreach (DB::table(Tables::TEMPLATE_FIELDS)->whereNotNull('settings')->get() as $row) {
            $settings = $this->rename(Value::map(json_decode(Value::string($row->settings), true)), $from, $to);
            DB::table(Tables::TEMPLATE_FIELDS)->where('template_id', $row->template_id)->where('field_id', $row->field_id)
                ->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return array<array-key, mixed>
     */
    private function rename(array $settings, string $from, string $to): array
    {
        foreach ($settings as $key => $value) {
            if (in_array($key, self::KEYS, true) && is_array($value)) {
                $settings[$key] = LocalizedText::from($value)->renameLanguage($from, $to)->toStorage();
            } elseif (is_array($value)) {
                $settings[$key] = $this->rename($value, $from, $to);
            }
        }

        return $settings;
    }
}
