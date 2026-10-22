<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

use LynxCms\Core\Models\Language;

/**
 * Dispatched after the action's transaction commits.
 *
 * @api
 */
final readonly class LanguageUpdated implements ContentChanged
{
    /**
     * @param  list<string>  $changed  the language columns that changed (fallback_id, name, ...)
     */
    public function __construct(public Language $language, public array $changed = []) {}

    /** Whether the language now falls back differently, which changes the values it shows. */
    public function changedFallback(): bool
    {
        return in_array('fallback_id', $this->changed, true);
    }

    /** Every page has a URL and texts in each language. */
    public function affectedPages(): null
    {
        return null;
    }
}
