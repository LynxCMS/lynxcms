<?php

declare(strict_types=1);

namespace LynxCms\Admin\Fields;

use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Template;

/**
 * The site's templates, for field settings that name templates (SettingFormats::TEMPLATE). Those
 * Lynx and its modules own are left out, as the New page screen leaves them out.
 *
 * @internal
 */
final readonly class FieldTemplateOptions
{
    public function __construct(private SchemaOwners $owners) {}

    /** @return list<array{name: string, label: string}> sorted by label */
    public function all(): array
    {
        return array_values(lynx()->templates->all()
            ->reject(fn (Template $template): bool => $this->owners->isSystemTemplate((string) $template->name))
            ->map(fn (Template $template): array => ['name' => (string) $template->name, 'label' => $template->label])
            ->sortBy('label')->all());
    }
}
