<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Templates\FamilyRules;

/**
 * The templates the New page screen offers (rules for which templates fit under which parent
 * are planned; until then every offered template fits anywhere). Modules hide templates that must be
 * created through their own screens: app(TemplateChoices::class)->exclude('menu', 'menu-item').
 * Singleton templates that already have their page are left out too.
 *
 * @api
 */
final class TemplateChoices
{
    /** @var array<string, true> */
    private array $excluded = [];

    public function exclude(string ...$templates): void
    {
        $this->excluded += array_fill_keys($templates, true);
    }

    /**
     * Templates a new page may use; under $parent, only those its family rules allow (ADR-0032).
     *
     * @return array<string, string> template name => label
     */
    public function for(?Page $parent = null): array
    {
        $choices = [];
        $rules = app(FamilyRules::class);

        foreach (lynx()->templates->all() as $template) {
            if ($this->isOffered($template) && (! $parent instanceof Page || $rules->allows($parent->template(), $template))) {
                $choices[(string) $template->name] = (string) ($template->label ?: $template->name);
            }
        }

        return $choices;
    }

    /**
     * The template most used among the parent's children, else the simple page template a new
     * site starts with, else the first choice.
     *
     * @param  array<string, string>  $choices
     */
    public function preferred(Page $parent, array $choices): ?string
    {
        $used = lynx()->pages->find("parent={$parent->id}, include=all, limit=50")->map(fn (Page $child): string => (string) $child->template()->name);
        $counts = array_count_values(array_values(array_filter($used, fn (string $name): bool => isset($choices[$name]))));
        arsort($counts);

        return array_key_first($counts)
            ?? (isset($choices[InstallLynx::SIMPLE_PAGE_TEMPLATE]) ? InstallLynx::SIMPLE_PAGE_TEMPLATE : array_key_first($choices));
    }

    private function isOffered(Template $template): bool
    {
        $name = (string) $template->name;

        // The 404 page's template is for that one page, not for new ones.
        if ($name === InstallLynx::NOT_FOUND_TEMPLATE || isset($this->excluded[$name]) || app(SchemaOwners::class)->isSystemTemplate($name)) {
            return false;
        }

        return ! $template->isSingleton() || lynx()->pages->count("template={$template->name}, include=all") === 0;
    }
}
