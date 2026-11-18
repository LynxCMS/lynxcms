<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

/**
 * An item's subfields in the groups their entries start ("group" on a subfield entry). Each group
 * runs to the next one; the fields before the first group form one without a heading.
 *
 * @api
 */
final class SubfieldGroups
{
    /**
     * @param  list<Subfield>  $subfields
     * @return list<SubfieldGroup>
     */
    public static function of(array $subfields): array
    {
        /** @var list<SubfieldGroup> $groups */
        $groups = [];

        foreach ($subfields as $subfield) {
            $last = array_pop($groups);
            $starts = $subfield->group();

            if ($last instanceof SubfieldGroup) {
                $groups[] = $starts instanceof SubfieldGroup ? $last : $last->with($subfield);
            }

            if ($starts instanceof SubfieldGroup || ! $last instanceof SubfieldGroup) {
                $groups[] = $starts ?? new SubfieldGroup('', '', '', [$subfield]);
            }
        }

        return $groups;
    }
}
