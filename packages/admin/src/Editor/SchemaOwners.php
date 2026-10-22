<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * Who may change templates and fields in the admin. Modules register the schema they own (the
 * menus module owns its menu templates and menu_* fields), which the screens show read only; and
 * the lynx-admin.schema_editing flag turns every schema screen read only, for sites whose schema
 * comes from YAML (lynx:schema:apply).
 *
 * @api
 */
final class SchemaOwners
{
    /** @var array<string, string> template name => module */
    private array $templates = [];

    /** @var array<string, string> field name => module */
    private array $fields = [];

    /**
     * @param  list<string>  $templates
     * @param  list<string>  $fields
     */
    public function register(string $module, array $templates = [], array $fields = []): void
    {
        $this->templates += array_fill_keys($templates, $module);
        $this->fields += array_fill_keys($fields, $module);
    }

    public function ownerOfTemplate(string $template): ?string
    {
        return $this->templates[$template] ?? null;
    }

    public function ownerOfField(string $field): ?string
    {
        return $this->fields[$field] ?? null;
    }

    /** System templates belong to Lynx or a module: hidden from lists unless asked, never offered for new pages. */
    public function isSystemTemplate(string $template): bool
    {
        return $this->ownerOfTemplate($template) !== null;
    }

    public function isSystemField(string $field): bool
    {
        return $this->ownerOfField($field) !== null;
    }

    public function isEditingEnabled(): bool
    {
        return (bool) config('lynx-admin.schema_editing', true);
    }

    public function canEditTemplate(string $template): bool
    {
        return $this->isEditingEnabled() && $this->ownerOfTemplate($template) === null;
    }

    public function canEditField(string $field): bool
    {
        return $this->isEditingEnabled() && $this->ownerOfField($field) === null;
    }
}
