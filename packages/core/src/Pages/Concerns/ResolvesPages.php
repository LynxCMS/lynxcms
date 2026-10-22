<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageReferences;
use LynxCms\Core\Support\Value;

/**
 * Resolves page references given as a Page, an id, a default-language path or a page name that
 * only one page has (PageReferences, ADR-0029).
 *
 * @internal
 */
trait ResolvesPages
{
    private function resolvePageReference(mixed $reference, string $key = 'page'): Page
    {
        if ($reference instanceof Page) {
            return $reference;
        }

        $id = is_int($reference) ? $reference : app(PageReferences::class)->single(Value::string($reference), $key);

        return ($id === null ? null : app(PageLoader::class)->find($id))
            ?? throw ValidationException::withMessages([$key => 'Page ['.(is_scalar($reference) ? $reference : '?').'] does not exist.']);
    }
}
