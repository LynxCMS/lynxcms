<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * JSON Schema "format" values that field types put on their settings, so forms and agents know
 * what a string holds beyond its type. Tools that don't know a format ignore it.
 *
 * @api
 */
final class SettingFormats
{
    /** A template name, such as a page field's "templates" items: the admin offers a template chooser. */
    public const string TEMPLATE = 'lynx-template';
}
