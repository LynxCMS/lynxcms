<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Strings\PageStrings;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use RuntimeException;

/**
 * Writes a content package (ADR-0040): manifest.json, the schema (schema/, as lynx:schema:export)
 * and pages.jsonl, one page per line, parents first. Pages are keyed by their default-language
 * names ("/courses/alpha/"), which also works for pages without URLs, and page references are
 * written as keys, so the package moves between sites. Files the values name go into files/
 * (ADR-0050). Drafts and the trash are left out.
 *
 * @api
 */
final readonly class ContentExporter
{
    public const string FORMAT = 'lynx-content';

    public const int VERSION = 1;

    private const int CHUNK = 500;

    public function __construct(
        private SchemaSync $schema,
        private PageLoader $loader,
        private LanguageRegistry $languages,
        private FieldRegistry $fields,
        private RootPage $root,
        private Trash $trash,
        private PackageFiles $files,
    ) {}

    /** @return int the number of pages written */
    public function export(string $directory): int
    {
        File::ensureDirectoryExists($directory);
        $this->schema->export($directory.'/schema');

        $keys = $this->keys();
        $handle = fopen($directory.'/pages.jsonl', 'w') ?: throw new RuntimeException("Cannot write {$directory}/pages.jsonl.");

        foreach (array_chunk(array_keys($keys), self::CHUNK) as $ids) {
            foreach ($this->loader->many($ids) as $page) {
                fwrite($handle, json_encode($this->line($page, $keys), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                $this->files->export($page, $directory);
            }

            $this->loader->flush();
        }

        fclose($handle);
        File::put($directory.'/manifest.json', json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'languages' => $this->languages->all()->map(fn ($language): string => (string) $language->code)->values()->all(),
            'pages' => count($keys),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return count($keys);
    }

    /**
     * Every page outside the trash, parents first, with its key.
     *
     * @return array<int, string> page id => key
     */
    public function keys(): array
    {
        $root = $this->root->id();

        if ($root === null) {
            return [];
        }

        $names = DB::table(Tables::PAGE_LANGUAGES)->where('language_id', $this->languages->default()->id)->pluck('name', 'page_id')->all();
        $rows = DB::table(Tables::PAGE_CLOSURE)
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', Tables::PAGE_CLOSURE.'.descendant_id')
            ->where(Tables::PAGE_CLOSURE.'.ancestor_id', $root)
            ->whereNull(Tables::PAGES.'.draft_of')
            ->orderBy(Tables::PAGE_CLOSURE.'.depth')->orderBy(Tables::PAGES.'.sort')->orderBy(Tables::PAGES.'.id')
            ->get([Tables::PAGES.'.id', Tables::PAGES.'.parent_id']);
        $trash = $this->trash->rootId();
        $keys = [];

        foreach ($rows as $row) {
            $id = Value::int($row->id);
            $parent = $row->parent_id === null ? null : Value::int($row->parent_id);

            if ($id === $trash || ($parent !== null && ! isset($keys[$parent]))) {
                continue;
            }

            $keys[$id] = $parent === null ? '/' : $keys[$parent].Value::string($names[$id] ?? (string) $id).'/';
        }

        return $keys;
    }

    /**
     * A page as a package line; the importer compares it with the package to skip unchanged pages.
     *
     * @param  array<int, string>  $keys  page id => key, from keys()
     * @return array<string, mixed>
     */
    public function line(Page $page, array $keys): array
    {
        $names = [];

        foreach ($this->languages->all() as $language) {
            $names[(string) $language->code] = ['name' => $page->name($language), 'active' => $page->isActiveIn($language)];
        }

        return [
            'key' => $keys[(int) $page->id],
            'parent' => $page->parentId === null ? null : ($keys[$page->parentId] ?? null),
            'template' => (string) $page->template()->name,
            'status' => array_values(array_diff(PageStatus::toNames($page->status), ['system'])),
            'names' => $names,
            'values' => $this->values($page, $keys),
        ] + (($strings = app(PageStrings::class)->pageEdits($page)) === [] ? [] : ['strings' => $strings]);
    }

    /**
     * Values by language code ("*" for untranslated fields), page references as keys.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function values(Page $page, array $keys): array
    {
        $values = $page->valuesByLanguage();

        foreach ($values as $code => $fields) {
            foreach ($fields as $name => $value) {
                $field = $this->fields->find((string) $name);
                $type = $field?->fieldType();

                if ($type instanceof ReferencesPages && $field !== null) {
                    $values[$code][$name] = $type->mapPageIds($value, fn (int|string $id): ?string => $keys[Value::int($id)] ?? null, $field->config());
                }
            }
        }

        return $values;
    }
}
