<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * A page's own string edits as page content (ADR-0048): pages.save writes them, drafts copy them,
 * revisions keep them. Site strings are left out: every page shares them, so they stay live.
 *
 * @internal
 */
final readonly class PageStringEdits
{
    private const int MAX_LENGTH = 10000;

    public function __construct(private StringCatalog $catalog, private StringStore $store, private LanguageRegistry $languages) {}

    /**
     * The page's own edits.
     *
     * @return array<string, array<string, string>> language code => key => text
     */
    public function snapshot(int $pageId): array
    {
        $site = $this->catalog->siteStrings();
        $edits = [];

        foreach (DB::table(Tables::STRINGS)->where('page_id', $pageId)->orderBy('key')->get(['key', 'language_id', 'value']) as $row) {
            $key = Value::string($row->key);
            $code = $this->languages->find(Value::int($row->language_id))?->code;

            if ($code !== null && ! isset($site[$key])) {
                $edits[(string) $code][$key] = Value::string($row->value);
            }
        }

        ksort($edits);

        return $edits;
    }

    /**
     * pages.save input that turns the page's own edits into $target: its texts, and null for
     * every edit the page has that $target lacks.
     *
     * @param  array<string, array<string, string>>  $target  language code => key => text
     * @return array<string, array<string, string|null>>
     */
    public function replacement(int $pageId, array $target): array
    {
        $input = $target;

        foreach ($this->snapshot($pageId) as $code => $texts) {
            foreach (array_keys($texts) as $key) {
                $input[$code][$key] ??= null;
            }
        }

        return $input;
    }

    /**
     * Errors in pages.save "strings" for a template, keyed like the input. Removing an edit is
     * always allowed, so restoring an old revision can clear strings the template since dropped.
     *
     * @return array<string, string>
     */
    public function errors(string $template, mixed $strings): array
    {
        $declared = array_filter($this->catalog->forTemplate($template), fn (StringDefinition $definition): bool => ! $definition->isSite());
        $errors = [];

        foreach (Value::map($strings) as $code => $texts) {
            if ($this->languages->find((string) $code) === null) {
                $errors["strings.{$code}"] = "There is no language [{$code}].";

                continue;
            }

            foreach (Value::map($texts) as $key => $text) {
                $error = $this->textError($key, $text, isset($declared[$key]), $template);

                if ($error !== null) {
                    $errors["strings.{$code}.{$key}"] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Writes edits; null or "" removes one.
     *
     * @param  array<string, mixed>  $strings  language code => key => text|null, already validated
     * @return list<string> what was written, e.g. "string hero.title [pt]"
     */
    public function write(int $pageId, array $strings, string $by): array
    {
        $written = [];

        foreach ($strings as $code => $texts) {
            $languageId = $this->languages->get($code)->id;

            foreach (Value::map($texts) as $key => $text) {
                $where = ['page_id' => $pageId, 'key' => $key, 'language_id' => $languageId];
                $current = DB::table(Tables::STRINGS)->where($where)->value('value');
                $text = $text === null || $text === '' ? null : Value::string($text);

                if ($current === $text) {
                    continue;
                }

                DB::table(Tables::STRINGS)->where($where)->delete();

                if ($text !== null) {
                    DB::table(Tables::STRINGS)->insert($where + ['value' => $text, 'updated_by' => $by, 'updated_at' => now()]);
                }

                $written[] = "string {$key} [{$code}]";
            }
        }

        $this->store->forget($pageId);

        return $written;
    }

    /** Copies one page's own edits to another, for a new draft. */
    public function copy(int $from, int $to, string $by): void
    {
        $this->write($to, $this->snapshot($from), $by);
    }

    /** Removes every edit stored on the page, for a deleted draft. */
    public function deleteAll(int $pageId): void
    {
        DB::table(Tables::STRINGS)->where('page_id', $pageId)->delete();
        $this->store->forget($pageId);
    }

    private function textError(string $key, mixed $text, bool $isDeclared, string $template): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (! $isDeclared) {
            return "Template [{$template}] declares no page string [{$key}]. Site texts are saved with strings.save.";
        }

        return is_string($text) && mb_strlen($text) <= self::MAX_LENGTH ? null : 'A string is text of at most '.self::MAX_LENGTH.' characters.';
    }
}
