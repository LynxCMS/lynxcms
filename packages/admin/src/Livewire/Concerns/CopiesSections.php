<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * The Copy to templates dialog of a section on the template board (ADR-0108): templates.copy-section
 * with the ticked templates. Each template shows why the section cannot go there, from a dry run,
 * and which of its fields would be renamed. The copy takes the section as last saved.
 */
trait CopiesSections
{
    /** The section the dialog is open for; empty while it is closed. */
    public string $copySection = '';

    /** Whether the template has the section saved, so there is something to copy. */
    public bool $isCopySectionSaved = false;

    /** @var list<string> the ticked templates */
    public array $copyTo = [];

    public bool $renameConflicts = false;

    public string $copyPrefix = '';

    public function openCopySection(string $key): void
    {
        $this->copySection = $key;
        $this->copyTo = [];
        $this->renameConflicts = false;
        $this->copyPrefix = str_replace('-', '_', $key).'_';
        $this->isCopySectionSaved = $this->savedSectionFields() !== [];
        $this->resetErrorBag();
        $this->dispatch('open-dialog-copy-section');
    }

    public function closeCopySection(): void
    {
        $this->copySection = '';
        $this->dispatch('close-dialog-copy-section');
    }

    public function copySectionTo(): void
    {
        $this->authorizeEditing();
        $copied = lynx()->templates->copySection((string) $this->template, $this->copySection, array_values(Value::strings($this->copyTo)), $this->renameConflicts, $this->copyPrefix !== '' ? $this->copyPrefix : null);
        $this->closeCopySection();
        $this->notify(trans_choice('lynx-admin::templates.copy.copied', is_array($copied) ? count($copied) : 0));
    }

    /**
     * Every template the section could go to: not this one, nor one a module owns.
     *
     * @return array<string, array{label: string, problem: string|null, renames: array<string, string>}>
     */
    private function copyTargets(): array
    {
        if ($this->copySection === '' || ! $this->isCopySectionSaved) {
            return [];
        }

        $owners = app(SchemaOwners::class);
        $targets = [];

        foreach (lynx()->templates->all()->sortKeys() as $name => $template) {
            if ($name !== $this->template && ! $owners->isSystemTemplate($name)) {
                $targets[$name] = $this->copyTarget($template);
            }
        }

        return $targets;
    }

    /**
     * @return array{label: string, problem: string|null, renames: array<string, string>}
     */
    private function copyTarget(Template $target): array
    {
        $clashes = array_values(array_intersect($this->savedSectionFields(), lynx()->templates->fields($target)->keys()->all()));

        return [
            'label' => $target->label,
            'problem' => $this->copyProblem($target->name),
            'renames' => array_combine($clashes, array_map(fn (string $name): string => $this->copyPrefix.$name, $clashes)),
        ];
    }

    /** Why the section cannot go to the template, as the action's dry run says; null when it can. */
    private function copyProblem(string $target): ?string
    {
        try {
            lynx()->templates->copySection((string) $this->template, $this->copySection, [$target], $this->renameConflicts, $this->copyPrefix !== '' ? $this->copyPrefix : null, dryRun: true);
        } catch (ValidationException $exception) {
            return $exception->validator->errors()->first();
        }

        return null;
    }

    /**
     * @return list<string> the section's fields as saved
     */
    private function savedSectionFields(): array
    {
        return array_values(lynx()->templates->fields((string) $this->template)
            ->filter(fn (TemplateField $field): bool => ($field->settings['section'] ?? null) === $this->copySection)
            ->keys()->all());
    }
}
