<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;

/**
 * Which templates may sit under which (ADR-0032), like ProcessWire's family settings: a parent
 * template may refuse children or limit their templates, and a template may limit its parents'.
 *
 * @api
 */
final readonly class FamilyRules
{
    public function __construct(private TemplateRegistry $templates, private PageLoader $pages) {}

    /** Whether a page using $child may be placed under a page using $parent. */
    public function allows(Template $parent, Template $child): bool
    {
        return $this->refusal($parent, $child) === null;
    }

    /**
     * Before a page using $child is created under, moved under or switched under $parentId.
     *
     * @param  string  $key  the input key the error belongs to
     */
    public function assertAllowed(Template $child, ?int $parentId, string $key): void
    {
        $parent = $parentId === null ? null : $this->pages->find($parentId);
        $refusal = $parent === null ? null : $this->refusal($this->templates->get($parent->templateId), $child);

        if ($refusal !== null) {
            throw ValidationException::withMessages([$key => $refusal]);
        }
    }

    private function refusal(Template $parent, Template $child): ?string
    {
        return self::refusalBetween((string) $parent->name, $parent->config(), (string) $child->name, $child->config());
    }

    /**
     * Why a page using $child may not sit under one using $parent, from the templates' settings,
     * or null: also for templates that exist only in a draft so far (lynx:draft's pages).
     *
     * @param  array<string, mixed>  $parentConfig
     * @param  array<string, mixed>  $childConfig
     */
    public static function refusalBetween(string $parent, array $parentConfig, string $child, array $childConfig): ?string
    {
        $children = Value::strings($parentConfig['child_templates'] ?? []);
        $parents = Value::strings($childConfig['parent_templates'] ?? []);

        return match (true) {
            ! (bool) ($parentConfig['allow_children'] ?? true) => "Pages using [{$parent}] cannot have children.",
            $children !== [] && ! in_array($child, $children, true) => "Under [{$parent}] pages may only use: ".implode(', ', $children).'.',
            $parents !== [] && ! in_array($parent, $parents, true) => "Pages using [{$child}] may only be placed under: ".implode(', ', $parents).'.',
            default => null,
        };
    }
}
