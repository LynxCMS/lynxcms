<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\PageLoader;

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
        return match (true) {
            ! $parent->allowsChildren() => "Pages using [{$parent->name}] cannot have children.",
            $parent->childTemplates() !== [] && ! in_array($child->name, $parent->childTemplates(), true) => "Under [{$parent->name}] pages may only use: ".implode(', ', $parent->childTemplates()).'.',
            $child->parentTemplates() !== [] && ! in_array($parent->name, $child->parentTemplates(), true) => "Pages using [{$child->name}] may only be placed under: ".implode(', ', $child->parentTemplates()).'.',
            default => null,
        };
    }
}
