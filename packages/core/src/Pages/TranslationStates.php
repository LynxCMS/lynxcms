<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * How each translatable field of a page reads in a language (ADR-0016, ADR-0102): who wrote it,
 * which provider, and whether the text a machine translated has changed since.
 *
 * @internal Read through lynx()->pages->translationStates().
 */
final class TranslationStates
{
    /**
     * Fields with a value in the language, in the template's order. Item fields keep no origin
     * of their own (ADR-0044), so they are not listed.
     *
     * @return array<string, array{origin: string, provider: string|null, outdated: bool}>
     */
    public function of(Page $page, Language $language): array
    {
        $rows = DB::table(Tables::FIELD_VALUE_META)
            ->where('page_id', $page->id)->where('language_id', $language->id)
            ->get(['field_id', 'origin', 'provider', 'outdated'])
            ->keyBy('field_id');
        $states = [];

        foreach ($page->template()->fields as $field) {
            $row = $rows->get($field->id);

            if ($row !== null) {
                $states[$field->name] = [
                    'origin' => Value::string($row->origin, 'human'),
                    'provider' => Value::nullableString($row->provider),
                    'outdated' => Value::bool($row->outdated),
                ];
            }
        }

        return $states;
    }
}
