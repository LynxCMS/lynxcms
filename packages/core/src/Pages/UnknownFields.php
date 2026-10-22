<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\Log;

/**
 * Debug help for templates: reading a field the schema does not have (a typo such as
 * $page->titel) returns null, as any missing field does, and in debug mode is logged once per
 * request. Fields that exist but are not on the page's template stay silent, as shared partials
 * read optional fields on purpose.
 *
 * @internal
 */
final class UnknownFields
{
    /** @var array<string, true> "template:field" pairs already logged */
    private array $reported = [];

    public function report(Page $page, string $field): void
    {
        if (config('app.debug') !== true || lynx()->fields->find($field) !== null) {
            return;
        }

        $template = (string) $page->template()->name;

        if (isset($this->reported["{$template}:{$field}"])) {
            return;
        }

        $this->reported["{$template}:{$field}"] = true;
        Log::warning("Lynx: Unknown field [{$field}] read on a page of template [{$template}]. It returns null; check the name.", [
            'field' => $field,
            'template' => $template,
            'page' => $page->url(),
        ]);
    }
}
