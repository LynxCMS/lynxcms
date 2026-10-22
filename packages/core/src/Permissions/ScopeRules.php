<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;

/**
 * Turns a user's grants for one permission into the PageScope that lists filter by (ADR-0033).
 *
 * @internal
 */
final readonly class ScopeRules
{
    public function __construct(private PageFinder $finder) {}

    /**
     * @param  list<RoleGrant>  $grants  the user's grants for the permission
     * @param  list<string>  $moduleTemplates  templates whose branches a module's permission the user holds covers
     * @return PageScope|null null when a grant has no limits
     */
    public function scope(array $grants, array $moduleTemplates): ?PageScope
    {
        $rules = [];

        foreach ($grants as $grant) {
            $templates = $grant->templates === null || $grant->templates === [] ? null : $this->templateIds($grant->templates);
            $branches = $grant->branches === null || $grant->branches === [] ? null : $grant->branches;

            if ($templates === null && $branches === null) {
                return null;
            }

            $rules[] = ['templates' => $templates, 'branches' => $branches];
        }

        if ($moduleTemplates !== []) {
            $rules[] = ['templates' => null, 'branches' => $this->finder->ids('template='.implode('|', $moduleTemplates).', include=all')];
        }

        return new PageScope($rules);
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function templateIds(array $names): array
    {
        return Value::ints(Template::query()->whereIn('name', $names)->pluck('id')->all());
    }
}
