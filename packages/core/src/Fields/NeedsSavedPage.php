<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * A field type whose values can only be made once the page exists, such as images stored in the
 * page's folder (ADR-0046). pages.save lets a page be created unpublished without such a field
 * even when it is required; every later save, and creating the page published, still needs it.
 *
 * @api
 */
interface NeedsSavedPage {}
