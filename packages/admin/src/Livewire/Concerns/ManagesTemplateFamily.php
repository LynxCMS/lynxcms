<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;

/**
 * The template form's Family section (has URL, singleton, children, child and parent templates,
 * templates pages may switch to, ADR-0032) and deleting the template after a dry run.
 *
 * @internal
 */
trait ManagesTemplateFamily
{
    public bool $hasUrl = true;

    public bool $singleton = false;

    public bool $allowChildren = true;

    /** @var list<string> */
    public array $childTemplates = [];

    /** @var list<string> */
    public array $parentTemplates = [];

    /** @var list<string> templates pages may switch to */
    public array $switchTemplates = [];

    #[Locked]
    public bool $isConfirmingDelete = false;

    /** @var list<string> */
    #[Locked]
    public array $deleteSummary = [];

    public function saveFamily(): void
    {
        $this->authorizeEditing();
        lynx()->templates->update((string) $this->template, [
            'has_url' => $this->hasUrl,
            'singleton' => $this->singleton,
            'allow_children' => $this->allowChildren,
            'child_templates' => array_values(Value::strings($this->childTemplates)),
            'parent_templates' => array_values(Value::strings($this->parentTemplates)),
            'allowed_templates' => array_values(Value::strings($this->switchTemplates)),
        ]);
        $this->notify('Saved.');
    }

    /** A dry run first; templates.delete refuses while pages use the template. */
    public function confirmDelete(): void
    {
        $this->authorizeEditing();
        $result = lynx()->templates->delete((string) $this->template, dryRun: true);
        $this->deleteSummary = $result instanceof ActionResult ? array_map(fn (Change $change): string => $change->summary, $result->changes->all()) : [];
        $this->isConfirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->isConfirmingDelete = false;
        $this->deleteSummary = [];
    }

    public function delete(): void
    {
        $this->authorizeEditing();
        lynx()->templates->delete((string) $this->template);
        $this->redirectRoute('lynx.admin.templates');
    }

    private function loadFamily(Template $template): void
    {
        $this->hasUrl = $template->hasUrl();
        $this->singleton = $template->isSingleton();
        $this->allowChildren = $template->allowsChildren();
        $this->childTemplates = $template->childTemplates();
        $this->parentTemplates = $template->parentTemplates();
        $this->switchTemplates = $template->allowedTemplates();
    }
}
