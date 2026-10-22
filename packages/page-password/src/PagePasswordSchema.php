<?php

declare(strict_types=1);

namespace LynxCms\PagePassword;

/**
 * The field the pagePassword module adds to templates that ask for it.
 *
 * @api
 */
final class PagePasswordSchema
{
    public const string FIELD = 'page_password';

    public const string TYPE = 'password';

    public const string LABEL = 'Password';

    public const string DESCRIPTION = 'Visitors need this password to open the page and the pages below it. Empty: the page is public, unless a page above it has a password.';
}
