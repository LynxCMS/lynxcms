<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * URL names for pages.save: sanitized, the default language's derived from the title when missing,
 * and written per language with the rule that siblings never share a name.
 *
 * @internal
 */
final readonly class NameWriter
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * The names to store, and errors keyed like the input ("names.pt").
     *
     * @return array{0: array<int, array{name: string, status: string}>, 1: array<string, string>}
     */
    public function prepare(Page $page, Language $default): array
    {
        $isRoot = $page->parentId === null;
        $names = $page->rawNames();
        $errors = [];

        foreach (array_intersect_key($names, $page->dirtyNames()) as $languageId => $entry) {
            $names[$languageId] = ['name' => $isRoot ? '' : PageName::from($entry['name']), 'status' => $entry['status']];

            if (! $isRoot && $names[$languageId]['name'] === '') {
                $errors["names.{$this->code($languageId)}"] = 'The page name cannot be empty.';
            }
        }

        if (! $isRoot && ($names[$default->id]['name'] ?? '') === '') {
            $this->deriveDefault($page, $default, $names, $errors);
        }

        $names[$default->id] = ['name' => $names[$default->id]['name'] ?? '', 'status' => 'published'];

        return [$names, $errors];
    }

    /**
     * Write the names that changed (all of them for a new page).
     *
     * @param  array<int, array{name: string, status: string}>  $names
     * @return bool whether any name was written, so paths need rebuilding
     */
    public function write(int $pageId, ?int $parentId, array $names, string $actor, CarbonInterface $now): bool
    {
        foreach ($names as $languageId => $entry) {
            $this->assertFreeAmongSiblings($pageId, $parentId, $languageId, $entry['name']);

            DB::table(Tables::PAGE_LANGUAGES)->updateOrInsert(
                ['page_id' => $pageId, 'language_id' => $languageId],
                ['parent_id' => $parentId, 'name' => $entry['name'], 'status' => $entry['status'], 'updated_at' => $now, 'updated_by' => $actor],
            );
        }

        return $names !== [];
    }

    /**
     * @param  array<int, array{name: string, status: string}>  $names
     * @param  array<string, string>  $errors
     */
    private function deriveDefault(Page $page, Language $default, array &$names, array &$errors): void
    {
        $derived = PageName::from(Value::string($page->getLanguageValue($default, 'title')));

        if ($derived !== '') {
            $names[$default->id] = ['name' => $derived, 'status' => 'published'];
            $page->setLanguageName($default, $derived);

            return;
        }

        $errors['names.'.$default->code] = $page->templateField('title') === null
            ? 'A name in the default language is required.'
            : 'A name or a title in the default language is required.';
    }

    private function assertFreeAmongSiblings(int $pageId, ?int $parentId, int $languageId, string $name): void
    {
        $isTaken = DB::table(Tables::PAGE_LANGUAGES)
            ->where('language_id', $languageId)
            ->where('name', $name)
            ->where('page_id', '!=', $pageId)
            ->where(fn (Builder $query): Builder => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId))
            ->exists();

        if ($isTaken) {
            throw ValidationException::withMessages(["names.{$this->code($languageId)}" => "Another page under the same parent already uses the name [{$name}]."]);
        }
    }

    private function code(int $languageId): string
    {
        return $this->languages->find($languageId)->code ?? (string) $languageId;
    }
}
