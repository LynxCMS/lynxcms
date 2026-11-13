<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Schema\SchemaOwners as CoreSchemaOwners;

/**
 * Who may change templates and fields in the admin. Which module owns a template or field comes
 * from core's registry (ADR-0115), and the screens show owned schema read only; the
 * lynx-admin.schema_editing flag turns every schema screen read only, for sites whose schema
 * comes from YAML (lynx:schema:apply).
 *
 * @api
 */
final readonly class SchemaOwners
{
    public function __construct(private CoreSchemaOwners $owners) {}

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
