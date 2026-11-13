<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Schema\SchemaSync;

/**
 * A draft as the items schema files hold (ADR-0116), each with its uuid: the site's for what it
 * has, a new one otherwise; and the pages to create once they are built.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @api
 */
final readonly class CompiledDraft
{
    /**
     * @param  list<FieldItem>  $fields
     * @param  list<TemplateItem>  $templates
     * @param  list<DraftPage>  $pages
     */
    public function __construct(public array $fields, public array $templates, public array $pages = []) {}
}
