<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

use LynxCms\Core\Models\Language;

/**
 * Dispatched after the action's transaction commits.
 *
 * @api
 */
final readonly class LanguageCreated implements ContentChanged
{
    public function __construct(public Language $language) {}

    /** Every page has a URL and texts in each language. */
    public function affectedPages(): null
    {
        return null;
    }
}
