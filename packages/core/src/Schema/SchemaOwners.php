<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

/**
 * Which templates and fields belong to Lynx or a module rather than to the site (ADR-0115). Each
 * module registers the schema it installs and depends on (the menus module owns its menu templates
 * and menu_* fields) from its service provider, so the registry is filled with or without the
 * admin. The admin shows owned schema read only and hides it from lists; lynx:templates and
 * lynx:fields list it apart from the site's. The first module to claim a name keeps it.
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
}
