<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Schema\SchemaOwners;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FamilyRules;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Reads a draft's pages section (ADR-0116): each URL path, with a title ("/blog: Blog"), nothing
 * (the title from the path), or {template, title, field: value}. The template defaults to the one
 * named like the path's last segment. Everything a save would refuse is refused here first, for
 * templates the draft is still to build too: a missing parent, a field the template lacks, a
 * required field without a value (files may wait), the family rules and singletons. A path the
 * site has is left as it is.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @internal
 */
final class DraftPages
{
    private const string HOME = '/';

    /** @var array<string, FieldItem> */
    private array $fieldItems = [];

    /** @var array<string, TemplateItem> */
    private array $templateItems = [];

    /** @var array<string, string> path => template, for the draft's pages so far and the site's */
    private array $placed = [];

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly FieldRegistry $fields,
        private readonly FieldTypeRegistry $types,
        private readonly SchemaOwners $owners,
    ) {}

    /**
     * @param  array<array-key, mixed>  $section  the draft's pages
     * @param  list<FieldItem>  $fields
     * @param  list<TemplateItem>  $templates
     * @return list<DraftPage>
     */
    public function compile(array $section, array $fields, array $templates): array
    {
        $this->fieldItems = array_column($fields, null, 'name');
        $this->templateItems = array_column($templates, null, 'name');
        $this->placed = [];
        $pages = [];

        foreach ($section as $path => $entry) {
            $pages[] = $this->page('/'.trim((string) $path, '/'), $entry);
        }

        return $pages;
    }

    private function page(string $path, mixed $entry): DraftPage
    {
        $segments = explode('/', trim($path, '/'));
        $name = end($segments);
        $parent = '/'.implode('/', array_slice($segments, 0, -1));
        $this->assertName($path, $name);

        $existing = lynx()->pages->get($path.'/');
        $entry = is_string($entry) ? ['title' => $entry] : Value::map($entry);
        $template = $existing instanceof Page ? (string) $existing->template()->name : $this->templateOf($path, $name, $entry);
        $values = array_diff_key($entry, ['template' => true]);

        if (! $existing instanceof Page) {
            // The title comes from the path when the template has one; a template without one takes none.
            $values = array_key_exists('title', $this->uses($template)) ? ['title' => DraftShorthand::label($name), ...$values] : $values;
            $this->assertPlaceable($path, $parent, $template, $values);
        }

        $this->placed[$path] = $template;

        return new DraftPage($path, $parent, $name, $template, $values);
    }

    private function assertName(string $path, string $name): void
    {
        if ($path === self::HOME) {
            throw new DraftError('Page [/]: the home page exists already. Change it in the admin.');
        }

        if ($name === '' || PageName::from($name) !== $name) {
            throw new DraftError("Page [{$path}]: [{$name}] is not a URL name. Use lowercase letters, digits and hyphens.");
        }
    }

    /** @param  array<string, mixed>  $entry */
    private function templateOf(string $path, string $name, array $entry): string
    {
        $template = Value::string($entry['template'] ?? $name);

        if ($this->config($template) === null || $this->owners->isSystemTemplate($template)) {
            $known = [...array_keys($this->templateItems), ...$this->templates->all()->map(fn (Template $t): string => (string) $t->name)->values()->all()];

            throw new DraftError("Page [{$path}]: there is no template [{$template}].".(isset($entry['template']) ? DraftError::suggestion($template, $known) : ' Give one: {template: name}.'));
        }

        return $template;
    }

    /** @param  array<string, mixed>  $values */
    private function assertPlaceable(string $path, string $parent, string $template, array $values): void
    {
        $parentTemplate = $this->placedTemplate($parent) ?? throw new DraftError("Page [{$path}]: its parent [{$parent}] does not exist. List it before.");
        $refusal = FamilyRules::refusalBetween($parentTemplate, $this->config($parentTemplate) ?? [], $template, $this->config($template) ?? []);

        if ($refusal !== null) {
            throw new DraftError("Page [{$path}]: {$refusal}");
        }

        $this->assertFields($path, $template, $values);
        $this->assertSingleton($path, $template);
    }

    /** The template of a page the site has or the draft placed before. */
    private function placedTemplate(string $path): ?string
    {
        if (isset($this->placed[$path])) {
            return $this->placed[$path];
        }

        $page = lynx()->pages->get($path === self::HOME ? self::HOME : $path.'/');

        return $page instanceof Page ? (string) $page->template()->name : null;
    }

    /** @param  array<string, mixed>  $values */
    private function assertFields(string $path, string $template, array $values): void
    {
        $uses = $this->uses($template);

        foreach (array_keys($values) as $field) {
            if (! array_key_exists((string) $field, $uses)) {
                throw new DraftError("Page [{$path}]: template [{$template}] has no field [{$field}].".DraftError::suggestion((string) $field, array_keys($uses)));
            }
        }

        foreach ($uses as $field => $isRequired) {
            if ($isRequired && ! isset($values[$field]) && ! $this->mayWait($field)) {
                throw new DraftError("Page [{$path}]: {$this->label($field)} is required.");
            }
        }
    }

    private function assertSingleton(string $path, string $template): void
    {
        if (! (bool) ($this->config($template)['singleton'] ?? false)) {
            return;
        }

        $hasOne = in_array($template, array_diff_key($this->placed, [$path => true]), true)
            || ($this->templates->find($template) instanceof Template && lynx()->pages->find("template={$template}, include=all, limit=1")->count() > 0);

        if ($hasOne) {
            throw new DraftError("Page [{$path}]: template [{$template}] is a singleton and already has a page.");
        }
    }

    /**
     * A template's settings, as the draft builds it or the site has it.
     *
     * @return array<string, mixed>|null
     */
    private function config(string $template): ?array
    {
        return isset($this->templateItems[$template]) ? Value::map($this->templateItems[$template]['settings']) : $this->templates->find($template)?->config();
    }

    /**
     * The template's fields, each with whether it is required there.
     *
     * @return array<string, bool>
     */
    private function uses(string $template): array
    {
        if (isset($this->templateItems[$template])) {
            return $this->requiredIn($this->templateItems[$template]['fields']);
        }

        return $this->templates->fields($template)->map(fn (TemplateField $use): bool => (bool) ($use->settings['required'] ?? false))->all();
    }

    /**
     * @param  list<mixed>  $entries  a template's field list, sections included
     * @return array<string, bool>
     */
    private function requiredIn(array $entries): array
    {
        $uses = [];

        foreach ($entries as $entry) {
            $entry = is_string($entry) ? ['name' => $entry] : Value::map($entry);
            $uses += isset($entry['section'])
                ? $this->requiredIn(Value::list($entry['fields'] ?? []))
                : [Value::string($entry['name'] ?? '') => (bool) ($entry['required'] ?? false)];
        }

        return $uses;
    }

    /** Files can only be added once the page is saved, so a required one may wait. */
    private function mayWait(string $field): bool
    {
        $type = $this->fieldItems[$field]['type'] ?? $this->fields->find($field)?->type;

        return is_string($type) && $this->types->has($type) && $this->types->get($type) instanceof NeedsSavedPage;
    }

    private function label(string $field): string
    {
        $label = isset($this->fieldItems[$field]) ? $this->fieldItems[$field]['label'] : $this->fields->find($field)?->labels()->toStorage();

        return LocalizedText::from($label)->in() ?: DraftShorthand::label($field);
    }
}
