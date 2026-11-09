<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Content\PageFiles;
use LynxCms\Core\Fields\CarriesFiles;
use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\PageTableType;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Strings\PageStringEdits;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The pages.save input that copies a page (pages.duplicate). The copy is saved twice: first its
 * names, status, strings and the values a new page can take, then, once it and the rest of its
 * branch have ids, the values that need them: files stored again in the copy's own folder, and
 * references to copied pages pointing at the copies. A page table keeps only items that were
 * copied too, so the copy never shares its items with the original.
 *
 * @internal
 */
final readonly class PageCopy
{
    /** What the copy's names end with when the original's are taken under the new parent. */
    public const string SUFFIX = '-copy';

    /** Flags a copy never carries: they belong to the one original page. */
    private const int NOT_COPIED = PageStatus::LOCKED | PageStatus::UNIQUE | PageStatus::SYSTEM;

    public function __construct(
        private TemplateRegistry $templates,
        private LanguageRegistry $languages,
        private Revisions $revisions,
        private PageStringEdits $strings,
        private Container $container,
    ) {}

    /**
     * Names, status, strings and the values that need no saved page, to create the copy. A
     * $titlePrefix ("[Copy]") goes before its title in every language that has one.
     *
     * @return array<string, mixed>
     */
    public function create(Page $page, int $parentId, bool $isUnpublished, ?string $titlePrefix = null): array
    {
        $values = array_diff_key($this->values($page), $this->later($page));

        if ($titlePrefix !== null && $titlePrefix !== '' && array_key_exists('title', $values)) {
            $values['title'] = $this->prefixed($values['title'], $titlePrefix);
        }

        return [
            'template' => (string) $page->template()->name,
            'parent' => $parentId,
            'names' => $this->names($page, $parentId),
            'status' => $this->status($page, $isUnpublished),
            'values' => $values,
            'strings' => $this->strings->snapshot((int) $page->id),
        ];
    }

    /** The title, or each language's title, with the prefix before it; empty titles stay empty. */
    private function prefixed(mixed $title, string $prefix): mixed
    {
        if (is_array($title)) {
            return array_map(fn (mixed $one): mixed => $this->prefixed($one, $prefix), $title);
        }

        return is_string($title) && $title !== '' ? $prefix.' '.$title : $title;
    }

    /**
     * The values saved once the copied branch has ids, with files and references rewritten.
     *
     * @param  array<int, int>  $copies  original id => copy id
     * @return array<string, mixed>
     */
    public function finish(Page $page, int $copyId, array $copies, bool $dryRun): array
    {
        $values = array_intersect_key($this->values($page), $this->later($page));

        foreach ($this->later($page) as $name => $templateField) {
            $type = $templateField->field->fieldType();
            $settings = $templateField->field->config();
            $values[$name] = $this->eachLanguage($templateField, $values[$name] ?? null, function (mixed $value) use ($type, $settings, $copies, $copyId, $dryRun): mixed {
                $value = $type instanceof ReferencesPages ? $this->references($type, $value, $settings, $copies) : $value;

                return $type instanceof CarriesFiles ? $type->mapFiles($value, fn (string $file): string => $this->copyFile($file, $copyId, $dryRun), $settings) : $value;
            });
        }

        return $values;
    }

    /**
     * Fields saved in the second step: those a new page cannot take, and those naming pages or files.
     *
     * @return array<string, TemplateField>
     */
    private function later(Page $page): array
    {
        return $this->templates->fields($page->templateId)
            ->filter(function (TemplateField $templateField): bool {
                $type = $templateField->field->fieldType();

                return $type instanceof NeedsSavedPage || $type instanceof ReferencesPages || $type instanceof CarriesFiles;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Page $page): array
    {
        return $this->revisions->saveInput($page, $page->valuesByLanguage());
    }

    /**
     * @param  Closure(mixed): mixed  $map
     */
    private function eachLanguage(TemplateField $templateField, mixed $value, Closure $map): mixed
    {
        if (! $templateField->field->isTranslatable()) {
            return $value === null ? null : $map($value);
        }

        return array_map(fn (mixed $one): mixed => $one === null ? null : $map($one), Value::map($value));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, int>  $copies
     */
    private function references(ReferencesPages $type, mixed $value, array $settings, array $copies): mixed
    {
        $isTable = $type instanceof PageTableType;

        return $type->mapPageIds($value, fn (int|string $id): int|string|null => $copies[(int) $id] ?? ($isTable ? null : $id), $settings);
    }

    private function copyFile(string $file, int $copyId, bool $dryRun): string
    {
        $store = $this->container->bound(PageFiles::class) ? $this->container->make(PageFiles::class) : null;
        $contents = $store?->read($file);

        return $store instanceof PageFiles && $contents !== null ? $store->write($copyId, $file, $contents, $dryRun) : $file;
    }

    /**
     * The original's name in each language, or, when a sibling has it, with SUFFIX and a number.
     *
     * @return array<string, array{name: string, active: bool}>
     */
    private function names(Page $page, int $parentId): array
    {
        $names = [];

        foreach ($page->rawNames() as $languageId => $row) {
            $code = $this->languages->find($languageId)?->code;

            if ($code !== null && $row['name'] !== '') {
                $names[(string) $code] = ['name' => $this->freeName($row['name'], $parentId, $languageId), 'active' => $page->isActiveIn((string) $code)];
            }
        }

        return $names;
    }

    private function freeName(string $name, int $parentId, int $languageId): string
    {
        $candidate = $name;

        for ($number = 1; $this->isTaken($candidate, $parentId, $languageId); $number++) {
            $candidate = $name.self::SUFFIX.($number > 1 ? '-'.$number : '');
        }

        return $candidate;
    }

    private function isTaken(string $name, int $parentId, int $languageId): bool
    {
        return DB::table(Tables::PAGE_LANGUAGES)->where('parent_id', $parentId)->where('language_id', $languageId)->where('name', $name)->exists();
    }

    /**
     * @return list<string>
     */
    private function status(Page $page, bool $isUnpublished): array
    {
        $flags = ($page->status & ~self::NOT_COPIED) | ($isUnpublished ? PageStatus::UNPUBLISHED : 0);

        return array_keys(array_filter(PageStatus::NAMES, fn (int $flag): bool => ($flags & $flag) !== 0));
    }
}
