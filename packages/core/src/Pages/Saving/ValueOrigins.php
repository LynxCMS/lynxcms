<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Where each translatable value comes from (ADR-0016): a person, a machine or a copy, with the
 * provider, the hash of the text a machine translated and that text's language. When the text a
 * translation was made from changes, the translation is outdated, until the text is back to what
 * was translated or the field is translated again (ADR-0102).
 *
 * @internal
 */
final readonly class ValueOrigins
{
    public function __construct(private LanguageRegistry $languages) {}

    /** Records how the value just written came to be; a value removed takes its record along. */
    public function record(int $pageId, Field $field, int $languageId, mixed $value, Input $input, CarbonInterface $now): void
    {
        $key = ['page_id' => $pageId, 'field_id' => $field->id, 'language_id' => $languageId];

        if ($value === null) {
            DB::table(Tables::FIELD_VALUE_META)->where($key)->delete();

            return;
        }

        $source = $input->string('source_language');
        DB::table(Tables::FIELD_VALUE_META)->updateOrInsert($key, [
            'origin' => $input->string('origin') ?? 'human',
            'provider' => $input->string('provider'),
            'source_hash' => $input->get("source_hashes.{$field->name}"),
            'source_language_id' => $source === null ? null : $this->languages->get($source)->id,
            'outdated' => false,
            'updated_at' => $now,
        ]);
    }

    /** The field's text in a language changed: machine translations made from it are outdated unless it reads as before. */
    public function sourceChanged(int $pageId, Field $field, int $languageId, mixed $value): void
    {
        $hash = $value === null ? null : hash('sha256', Value::string($value));
        $translations = DB::table(Tables::FIELD_VALUE_META)
            ->where('page_id', $pageId)->where('field_id', $field->id)
            ->where('origin', 'machine')->where('source_language_id', $languageId);

        $translations->clone()->where('source_hash', $hash)->update(['outdated' => false]);
        $translations->clone()->where(fn (Builder $query): Builder => $query->whereNull('source_hash')->orWhere('source_hash', '!=', $hash))->update(['outdated' => true]);
    }
}
