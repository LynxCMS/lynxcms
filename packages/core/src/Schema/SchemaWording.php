<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

/**
 * Where a module keeps translations of the labels it installs (ADR-0140). A module registers its
 * translation namespace with the templates and fields it adds, from its service provider, and ships
 * lang/{locale}/schema.php: the text it installs => the translation. Editors read it in their own
 * language. Text the site changed is left as the site wrote it.
 *
 * @api
 */
final class SchemaWording
{
    /** @var array<string, string> template name => namespace */
    private array $templates = [];

    /** @var array<string, string> field name => namespace */
    private array $fields = [];

    /**
     * @param  list<string>  $templates
     * @param  list<string>  $fields
     */
    public function register(string $namespace, array $templates = [], array $fields = []): void
    {
        $this->templates += array_fill_keys($templates, $namespace);
        $this->fields += array_fill_keys($fields, $namespace);
    }

    public function ofTemplate(string $template): ?string
    {
        return $this->templates[$template] ?? null;
    }

    public function ofField(string $field): ?string
    {
        return $this->fields[$field] ?? null;
    }
}
