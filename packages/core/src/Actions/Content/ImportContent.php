<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Content;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Content\ContentPackage;
use LynxCms\Core\Content\PackageFiles;
use LynxCms\Core\Content\PackageReferences;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\Revisions;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Schema\SchemaSyncResult;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Imports a content package (ADR-0040) through pages.save: pages are matched by key, created when
 * missing and updated when they differ; page references, and files stored for the page they
 * land on (ADR-0050), are set once every page exists. With
 * "with_schema", the package's languages, fields and templates come first and its roles last, once
 * the pages their grants are limited to exist; a schema error stops the import. With
 * "prune", pages not in the package go to the trash. A page that cannot be written (a locked one,
 * another template) is skipped with the reason, and the rest still imports.
 */
final class ImportContent extends Action
{
    /** @var array<string, int> key => page id */
    private array $ids = [];

    /** @var array{created: int, updated: int, unchanged: int, trashed: int, skipped: list<string>} */
    private array $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'trashed' => 0, 'skipped' => []];

    public function __construct(
        private readonly ContentExporter $exporter,
        private readonly PageLoader $loader,
        private readonly TemplateRegistry $templates,
        private readonly PackageReferences $references,
        private readonly Revisions $revisions,
        private readonly PackageFiles $files,
        private readonly SchemaSync $schema,
    ) {}

    public static function name(): string
    {
        return 'content.import';
    }

    public static function description(): string
    {
        return 'Import a content package written by lynx:export or lynx:backup.';
    }

    /** It reads files on the server: a console and admin concern. */
    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['path' => ['required', 'string'], 'prune' => ['sometimes', 'boolean'], 'with_schema' => ['sometimes', 'boolean']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Directory of an extracted content package.')->required(),
            'prune' => $schema->boolean()->description('Move pages that are not in the package to the trash.'),
            'with_schema' => $schema->boolean()->description('Also apply the package\'s languages, fields, templates and roles.'),
        ];
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, trashed: int, skipped: list<string>}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $package = ContentPackage::open((string) $input->string('path'));
        $withSchema = $input->bool('with_schema');

        if ($withSchema) {
            $this->apply($this->schema->applyStructure($package->schemaDirectory()), $context);
        }

        $existing = $this->exporter->keys();
        $this->ids = array_flip($existing);
        $references = [];

        $strings = [];

        foreach ($package->pages() as $line) {
            if ($this->importPage($line, $existing, $context) && $this->references->of($line) !== []) {
                $references[] = $line;
            }

            if (isset($line['strings'])) {
                $strings[] = $line;
            }
        }

        foreach ($references as $line) {
            $id = $this->ids[Value::string($line['key'])];
            $values = $this->files->import($this->references->resolve($this->references->of($line), fn (int|string $key): ?int => $this->ids[(string) $key] ?? null), $package->directory, $id, $context->dryRun);
            $this->attempt(Value::string($line['key']), fn (): mixed => $context->run(SavePage::class, ['id' => $id, 'values' => $values]));
        }

        foreach ($strings as $line) {
            $this->importStrings($line, $context);
        }

        if ($withSchema) {
            $this->apply($this->schema->applyRoles($package->schemaDirectory()), $context);
        }

        if ($input->bool('prune')) {
            $this->prune($existing, $package, $context);
        }

        $context->changes->updated('content', null, sprintf('Import content: %d created, %d updated, %d trashed', $this->result['created'], $this->result['updated'], $this->result['trashed']), $this->result);

        return $this->result;
    }

    /** Records a schema step's changes; any error stops the import, and nothing is written. */
    private function apply(SchemaSyncResult $result, ActionContext $context): void
    {
        if ($result->hasErrors()) {
            throw ValidationException::withMessages(['path' => $result->errors]);
        }

        foreach ($result->changes as $change) {
            $context->changes->schema('schema', $change);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $existing
     * @return bool whether the page was written
     */
    private function importPage(array $line, array $existing, ActionContext $context): bool
    {
        $key = Value::string($line['key'] ?? '');
        $id = $this->ids[$key] ?? null;

        if ($id === null) {
            return $this->create($key, $line, $context);
        }

        $page = $this->loader->find($id);

        if (! $page instanceof Page || $page->template()->name !== $line['template']) {
            $this->result['skipped'][] = "{$key}: it uses another template here; switch it first.";

            return false;
        }

        if ($this->exporter->line($page, $existing) == $line) {
            $this->result['unchanged']++;

            return false;
        }

        return $this->attempt($key, function () use ($context, $id, $page, $line): void {
            $context->run(SavePage::class, ['id' => $id, 'names' => $line['names'], 'values' => $this->ownValues($page, $line), 'status' => $line['status']]);
            $this->result['updated']++;
        });
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function create(string $key, array $line, ActionContext $context): bool
    {
        $parent = $this->ids[Value::string($line['parent'] ?? '')] ?? null;
        $template = $this->templates->find(Value::string($line['template'] ?? ''));
        // The home page has no parent: on a site without pages, the package's home page creates it.
        $isHome = ($line['parent'] ?? null) === null;

        if (($parent === null && ! $isHome) || $template === null) {
            $this->result['skipped'][] = "{$key}: ".($template === null ? 'its template does not exist here (import with the schema).' : 'its parent was not imported.');

            return false;
        }

        return $this->attempt($key, function () use ($context, $key, $line, $parent, $template): void {
            $page = $context->run(SavePage::class, [
                'template' => $template->name,
                'parent' => $parent,
                'names' => $line['names'],
                'values' => $this->ownValues(new Page((int) $template->id, $parent), $line),
                'status' => $line['status'],
            ]);
            $this->ids[$key] = $page instanceof Page ? (int) $page->id : 0;
            $this->result['created']++;
        });
    }

    /**
     * The line's values as pages.save takes them, without page references (set later).
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function ownValues(Page $page, array $line): array
    {
        $stored = [];

        foreach (Value::map($line['values'] ?? []) as $code => $fields) {
            $stored[(string) $code] = Value::map($fields);
        }

        return $this->references->without($this->revisions->saveInput($page, $stored));
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function prune(array $existing, ContentPackage $package, ActionContext $context): void
    {
        $kept = [];

        foreach ($package->pages() as $line) {
            $kept[Value::string($line['key'])] = true;
        }

        $pruned = [];

        foreach ($existing as $id => $key) {
            $page = $this->loader->find($id);
            $isUnderPruned = array_any(array_keys($pruned), fn (string $above): bool => str_starts_with($key, $above));

            if (isset($kept[$key]) || $key === '/' || $isUnderPruned || ! $page instanceof Page || $page->hasStatus(PageStatus::SYSTEM)) {
                continue;
            }

            if ($this->attempt($key, fn (): mixed => $context->run(TrashPage::class, ['page' => $id]))) {
                $pruned[$key] = true;
                $this->result['trashed']++;
            }
        }
    }

    /**
     * The page's edited template strings (ADR-0047), per language.
     *
     * @param  array<string, mixed>  $line
     */
    private function importStrings(array $line, ActionContext $context): void
    {
        $key = Value::string($line['key'] ?? '');
        $id = $this->ids[$key] ?? null;

        foreach ($id === null ? [] : Value::map($line['strings']) as $code => $values) {
            $this->attempt($key, fn (): mixed => $context->run(SaveStrings::class, ['page' => $id, 'language' => $code, 'values' => Value::map($values)]));
        }
    }

    private function attempt(string $key, Closure $write): bool
    {
        try {
            $write();

            return true;
        } catch (ValidationException $e) {
            $this->result['skipped'][] = "{$key}: ".$e->getMessage();

            return false;
        }
    }
}
