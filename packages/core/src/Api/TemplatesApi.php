<?php

declare(strict_types=1);

namespace LynxCms\Core\Api;

use Illuminate\Support\Collection;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Templates\CopySection;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * $lynx->templates
 *
 * @api
 */
final readonly class TemplatesApi
{
    public function __construct(
        private ActionRunner $runner,
        private TemplateRegistry $registry,
    ) {}

    /**
     * @param  list<string|array<string, mixed>>  $fields
     * @param  string|array<string, string>|null  $label  text, or language code => text (ADR-0043)
     * @param  list<string>  $allowedTemplates
     */
    public function add(
        string $name,
        array $fields = [],
        string|array|null $label = null,
        ?string $from = null,
        ?bool $hasUrl = null,
        ?bool $singleton = null,
        ?array $allowedTemplates = null,
        bool $dryRun = false,
    ): Template|ActionResult {
        $input = array_filter([
            'name' => $name,
            'label' => $label,
            'from' => $from,
            'fields' => $fields,
            'has_url' => $hasUrl,
            'singleton' => $singleton,
            'allowed_templates' => $allowedTemplates,
        ], fn (mixed $v): bool => $v !== null);

        return $this->runner->run(CreateTemplate::class, $input, $dryRun)->valueOrDryRun(Template::class);
    }

    /**
     * @param  array<string, mixed>  $changes  label, fields, add, remove, has_url, singleton, allowed_templates
     */
    public function update(Template|string $template, array $changes, bool $dryRun = false): Template|ActionResult
    {
        return $this->runner->run(UpdateTemplate::class, ['template' => $this->name($template)] + $changes, $dryRun)->valueOrDryRun(Template::class);
    }

    public function delete(Template|string $template, bool $dryRun = false): Template|ActionResult
    {
        return $this->runner->run(DeleteTemplate::class, ['template' => $this->name($template)], $dryRun)->valueOrDryRun(Template::class);
    }

    /**
     * Copy a section, with its fields and their settings, into other templates (ADR-0108). With
     * $renameConflicts, a field a template already has is cloned as $prefix.name for it.
     *
     * @param  list<string>  $to
     * @return list<string>|ActionResult the templates the section was copied to, or the dry run
     */
    public function copySection(Template|string $template, string $section, array $to, bool $renameConflicts = false, ?string $prefix = null, bool $dryRun = false): array|ActionResult
    {
        $input = ['template' => $this->name($template), 'section' => $section, 'to' => $to, 'rename_conflicts' => $renameConflicts]
            + ($prefix === null ? [] : ['prefix' => $prefix]);
        $result = $this->runner->run(CopySection::class, $input, $dryRun);

        return $result->dryRun ? $result : Value::strings($result->arrayValue());
    }

    public function get(string $name): Template
    {
        return $this->registry->get($name);
    }

    public function find(string $name): ?Template
    {
        return $this->registry->find($name);
    }

    /**
     * @return Collection<string, Template>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    /**
     * @return Collection<string, TemplateField>
     */
    public function fields(Template|string $template): Collection
    {
        return $this->registry->fields($template);
    }

    private function name(Template|string $template): string
    {
        return $template instanceof Template ? $template->name : $template;
    }
}
