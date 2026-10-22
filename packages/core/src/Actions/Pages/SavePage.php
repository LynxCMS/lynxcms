<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\EditLocks;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Pages\Saving\NameWriter;
use LynxCms\Core\Pages\Saving\PageInput;
use LynxCms\Core\Pages\Saving\PageRowWriter;
use LynxCms\Core\Pages\Saving\ValueWriter;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Pages\UniqueNames;
use LynxCms\Core\Strings\PageStringEdits;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FamilyRules;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Creates or updates a page: names per language, field values per language, status, and the
 * page's own template strings (ADR-0048).
 * Writes only what changed, normalizes every value, records its origin (ADR-0016), checks
 * required fields in the default language, and rebuilds paths when names change. The work is
 * split between Pages\Saving\PageInput, NameWriter, ValueWriter and PageRowWriter.
 *
 * Input is either a Page object (from the PHP API) or plain arrays (from MCP and the AI agent).
 */
final class SavePage extends Action
{
    public function __construct(
        private readonly PageGuard $guard,
        private readonly LanguageRegistry $languages,
        private readonly TemplateRegistry $templates,
        private readonly PageLoader $loader,
        private readonly PathBuilder $paths,
        private readonly PathResolver $resolver,
        private readonly UniqueNames $uniqueNames,
        private readonly PageInput $pageInput,
        private readonly NameWriter $names,
        private readonly ValueWriter $values,
        private readonly PageRowWriter $rows,
        private readonly FamilyRules $family,
        private readonly Trash $trash,
        private readonly Revisions $revisions,
        private readonly Drafts $drafts,
        private readonly PageStringEdits $strings,
    ) {}

    public static function name(): string
    {
        return 'pages.save';
    }

    public static function permission(): string
    {
        return 'pages.edit|pages.create';
    }

    public static function description(): string
    {
        return 'Create or update a page. For a new page give "template" and "parent" (id or path, "/" for the home page). '
            .'"names" sets the URL name per language code ({"default": "my-course", "pt": "meu-curso"}). '
            .'"values" sets field values: shared fields take a value; translatable fields take a value for "language" '
            .'(default: current language) or an object keyed by language code. null removes a value so it falls back; "" stores an explicit empty value.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['nullable'],
            'id' => ['nullable', 'integer'],
            'expected_revision' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'template' => ['nullable', 'string'],
            'parent' => ['nullable'],
            'language' => ['nullable', 'string'],
            'names' => ['sometimes', 'array'],
            'values' => ['sometimes', 'array'],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::in(array_keys(PageStatus::NAMES))],
            'origin' => ['sometimes', Rule::in(['human', 'machine', 'copied'])],
            'provider' => ['sometimes', 'nullable', 'string'],
            'source_hashes' => ['sometimes', 'array'],
            'strings' => ['sometimes', 'array'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing page to update. Omit to create a page.'),
            'expected_revision' => $schema->integer()->description('The page revision you loaded (get_page returns it). If the page was saved since, the save is refused instead of overwriting those changes.'),
            'template' => $schema->string()->description('Template name, required for new pages.'),
            'parent' => $schema->string()->description('Parent page id or path in the default language, e.g. "/courses/". Required for new pages.'),
            'language' => $schema->string()->description('Language code for plain values of translatable fields. Default: current language.'),
            'names' => $schema->object()->description('URL name per language code, e.g. {"default": "delivery-8", "pt": "delivery-8-pt"}. A value may also be {"name": "...", "active": false}.'),
            'values' => $schema->object()->description('Field values. Translatable fields accept {"<language code>": value}.'),
            'status' => $schema->array()->items($schema->string()->enum(array_keys(PageStatus::NAMES)))->description('Status flags, replacing the current ones.'),
            'strings' => $schema->object()->description('The page\'s template texts (get_page "strings", page scope) by language code: {"pt": {"hero.title": "Texto"}}; null removes an edit.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->pageInput->page($input);
        $template = $this->templates->get($page->templateId);
        $isNew = $page->id === null;
        $default = $this->languages->default();

        if (! $isNew) {
            $this->guard->assertEditable($page);
            $this->guard->keepSystemFlag($page);
            $this->assertCurrent($page, $input->int('expected_revision'));
        }

        $this->assertPlacement($page, $template, $isNew);
        $this->authorize($context, $page, $template, $isNew);
        [$names, $nameErrors] = $this->names->prepare($page, $default);
        [$values, $valueErrors] = $this->values->prepare($page, $template, $default);
        $stringErrors = $this->strings->errors($template->name, $input->get('strings') ?? []);

        if ($nameErrors + $valueErrors + $stringErrors !== []) {
            throw ValidationException::withMessages($nameErrors + $valueErrors + $stringErrors);
        }

        $changedNames = $isNew ? $names : array_intersect_key($names, $page->dirtyNames());
        $this->assertUniqueName($page->id, $page->hasStatus(PageStatus::UNIQUE), isset($changedNames[$default->id]), $names[$default->id]['name'], $default);

        $actor = $context->actor->kind.($context->actor->id !== null ? ':'.$context->actor->id : '');
        $now = now();
        $id = $this->rows->write($page, $actor, $now);
        $namesChanged = $this->names->write($id, $page->parentId, $changedNames, $actor, $now);
        $written = [...$this->values->write($id, $page, $values, $input, $now), ...$this->strings->write($id, Value::map($input->get('strings') ?? []), $actor)];

        $page->hydrate($values, $names);
        $this->loader->forget($id);
        $this->recordChange($context, $page, $template, $isNew, $written, $input->string('origin') ?? 'human');

        // A draft's saves are not revisions: publishing it makes one on the live page (ADR-0039).
        if ($written !== [] && ! $this->drafts->isDraft($id)) {
            $this->revisions->record($id, $page->revision, EditLocks::holder($context->actor));
        }

        if ($namesChanged) {
            $this->resolver->flush();
            $this->paths->rebuild($id, descendants: ! $isNew, changes: $context->changes);
        }

        $context->event(new PageSaved($id, $isNew));

        return $page;
    }

    /**
     * Permissions (ADR-0033): pages.create under the parent for the new page's template, or
     * pages.edit on the page; pages.publish when the save publishes or unpublishes (a new page
     * not created unpublished counts as publishing); pages.status for hidden and locked.
     */
    private function authorize(ActionContext $context, Page $page, Template $template, bool $isNew): void
    {
        $isNew
            ? $context->authorize('pages.create', $page->parentId === null ? null : $this->loader->find($page->parentId), $template)
            : $context->authorize('pages.edit', $page);

        $stored = $isNew ? PageStatus::UNPUBLISHED : Value::int(DB::table(Tables::PAGES)->where('id', $page->id)->value('status'));
        $changed = ($stored ^ $page->status) & ~PageStatus::SYSTEM;
        $target = $isNew ? null : $page;

        if (($changed & PageStatus::UNPUBLISHED) !== 0) {
            $context->authorize('pages.publish', $target ?? ($page->parentId === null ? null : $this->loader->find($page->parentId)), $isNew ? $template : null);
        }

        if (($changed & ~PageStatus::UNPUBLISHED) !== 0) {
            $context->authorize('pages.status', $target ?? ($page->parentId === null ? null : $this->loader->find($page->parentId)), $isNew ? $template : null);
        }
    }

    /** Singleton templates have one page, only the home page has no parent, the parent exists, and family rules allow it (ADR-0032). */
    private function assertPlacement(Page $page, Template $template, bool $isNew): void
    {
        if ($isNew && $template->isSingleton() && DB::table(Tables::PAGES)->where('template_id', $template->id)->whereNull('draft_of')->exists()) {
            throw ValidationException::withMessages(['template' => "Template [{$template->name}] is a singleton and already has a page."]);
        }

        if ($isNew && $page->parentId === null && DB::table(Tables::PAGES)->whereNull('parent_id')->whereNull('draft_of')->exists()) {
            throw ValidationException::withMessages(['parent' => 'A parent page is required.']);
        }

        if ($page->parentId !== null && ! DB::table(Tables::PAGES)->where('id', $page->parentId)->exists()) {
            throw ValidationException::withMessages(['parent' => "Parent page [{$page->parentId}] does not exist."]);
        }

        if ($isNew && $page->parentId !== null && $this->trash->holds($page->parentId)) {
            throw ValidationException::withMessages(['parent' => 'Pages cannot be created in the trash.']);
        }

        if ($isNew) {
            $this->family->assertAllowed($template, $page->parentId, 'parent');
        }
    }

    /**
     * The page change comes first: the activity log files the action under its first change.
     *
     * @param  list<string>  $written
     */
    private function recordChange(ActionContext $context, Page $page, Template $template, bool $isNew, array $written, string $origin): void
    {
        $label = $page->name($this->languages->default()) !== '' ? $page->name($this->languages->default()) : 'home';
        $summary = ($isNew ? "Create {$template->name} page {$label}" : "Update page {$label}").($written !== [] ? ': '.implode(', ', $written) : '');

        $isNew
            ? $context->changes->created('page', $page->id, $summary, ['template' => $template->name, 'fields' => $written, 'origin' => $origin])
            : $context->changes->updated('page', $page->id, $summary, ['fields' => $written, 'origin' => $origin]);
    }

    /** Optimistic locking: refuse to overwrite a save made after the caller loaded the page. */
    private function assertCurrent(Page $page, ?int $expected): void
    {
        $row = $expected === null ? null : DB::table(Tables::PAGES)->where('id', $page->id)->first(['revision', 'modified_by', 'updated_at']);

        if ($row !== null && Value::int($row->revision) !== $expected) {
            throw ValidationException::withMessages(['conflict' => sprintf(
                'Page [%s] was saved by %s at %s after you loaded it. Reload it to see those changes; your edits were not saved.',
                $page->name(),
                Value::string($row->modified_by, 'someone'),
                Value::string($row->updated_at),
            )]);
        }
    }

    /** A new default-language name, or a newly added unique flag, must respect unique pages (ADR-0028). */
    private function assertUniqueName(?int $pageId, bool $isUnique, bool $isRenamed, string $name, Language $default): void
    {
        $wasUnique = $pageId !== null && (Value::int(DB::table(Tables::PAGES)->where('id', $pageId)->value('status')) & PageStatus::UNIQUE) !== 0;

        if ($isRenamed || ($isUnique && ! $wasUnique)) {
            $this->uniqueNames->assertAllowed($pageId, $name, $isUnique, 'names.'.$default->code);
        }
    }
}
