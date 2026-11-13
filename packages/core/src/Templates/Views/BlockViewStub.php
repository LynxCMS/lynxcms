<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Models\Field;

/**
 * A starting view for one block type of a matrix field (lynx:make:view, lynx:draft): the type's
 * fields in order, read from $block, which @matrix renders with $page and $loop too.
 *
 * @internal
 */
final readonly class BlockViewStub
{
    private const string INDENT = '    ';

    public function __construct(private FieldSnippet $snippets) {}

    public function for(Field $matrix, string $type): string
    {
        $matrixType = $matrix->fieldType();
        $config = $matrix->config();
        $label = $matrixType instanceof MatrixType ? $matrixType->typeLabel($config, $type) : $type;
        $lines = ["{{-- {$label} block of {$matrix->name} (\$block, \$page, \$loop): written by lynx:make:view, yours to change. --}}"];

        foreach ($matrixType instanceof MatrixType ? $matrixType->subfields($config, $type) : [] as $subfield) {
            $lines[] = '';
            array_push($lines, ...$this->snippets->lines('$block', $subfield->field, $subfield->config()));
        }

        $body = array_map(fn (string $line): string => $line === '' ? '' : self::INDENT.$line, $lines);

        return implode("\n", ['<div>', ...$body, '</div>'])."\n";
    }
}
