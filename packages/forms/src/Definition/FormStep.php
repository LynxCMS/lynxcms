<?php

declare(strict_types=1);

namespace LynxCms\Forms\Definition;

use LynxCms\Core\Support\LocalizedText;

/**
 * One step of a multi-step form: a title and the fields it asks for, in order.
 *
 * @api
 */
final readonly class FormStep
{
    public LocalizedText $title;

    /**
     * @param  string|array<string, string>  $title  text, or text by language code
     * @param  list<string>  $fields  field names
     */
    public function __construct(public string $key, string|array $title, public array $fields)
    {
        $this->title = LocalizedText::from($title);
    }
}
