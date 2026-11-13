<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Schema\SchemaEditing;
use LynxCms\Core\Schema\SchemaOwners as CoreSchemaOwners;

/**
 * Who may change templates and fields in the admin, as core decides it: which module owns a
 * template or field comes from core's registry (ADR-0115), and lynx.schema_editing off makes
 * every schema screen read only, as the actions refuse the change anyway (ADR-0117).
 *
 * @api
 */
final readonly class SchemaOwners
{
    public function __construct(private CoreSchemaOwners $owners, private SchemaEditing $editing) {}

    /**
     * @deprecated Register on LynxCms\Core\Schema\SchemaOwners from the module's service provider,
     *             so the schema is known without the admin too (ADR-0115).
     *
     * @param  list<string>  $templates
     * @param  list<string>  $fields
     */
    public function register(string $module, array $templates = [], array $fields = []): void
    {
        $this->owners->register($module, $templates, $fields);
    }

    public function ownerOfTemplate(string $template): ?string
    {
        return $this->owners->ownerOfTemplate($template);
    }

    public function ownerOfField(string $field): ?string
    {
        return $this->owners->ownerOfField($field);
    }

    /** System templates belong to Lynx or a module: hidden from lists unless asked, never offered for new pages. */
    public function isSystemTemplate(string $template): bool
    {
        return $this->owners->isSystemTemplate($template);
    }

    public function isSystemField(string $field): bool
    {
        return $this->owners->isSystemField($field);
    }

    public function isEditingEnabled(): bool
    {
        return $this->editing->isEnabled();
    }

    public function canEditTemplate(string $template): bool
    {
        return $this->editing->canEditTemplate($template);
    }

    public function canEditField(string $field): bool
    {
        return $this->editing->canEditField($field);
    }
}
