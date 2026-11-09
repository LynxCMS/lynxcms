<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use Illuminate\Support\Str;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * The Blade that prints one field in a starting view (lynx:make:view), the way the field's type
 * reads in templates: text through @field, dates formatted, references and repeater items looped,
 * images with <x-lynx-image>, blocks with @matrix. A password is never printed; a type it does not
 * know gets a comment pointing to the Field Types page.
 *
 * @internal
 */
final class FieldSnippet
{
    private const string INDENT = '    ';

    /**
     * @param  string  $owner  the variable the field is read from, e.g. "$page"
     * @param  array<string, mixed>  $config  the field's settings where it is used
     * @return list<string>
     */
    public function lines(string $owner, Field $field, array $config): array
    {
        $value = $owner.'->'.$field->name;

        return match ($field->type) {
            'text' => $field->name === 'title' && $owner === '$page' ? ["<h1>@field({$value})</h1>"] : $this->ifFilled($value, ["<p>@field({$value})</p>"]),
            'textarea' => $this->ifFilled($value, [($config['format'] ?? 'plain') === 'plain' ? "<p>@field({$value})</p>" : "<div>@field({$value})</div>"]),
            'url' => $this->ifFilled($value, ["<a href=\"{{ {$value} }}\">{{ {$value} }}</a>"]),
            'email' => $this->ifFilled($value, ["<a href=\"mailto:{{ {$value} }}\">{{ {$value} }}</a>"]),
            'integer', 'decimal', 'time' => ["@if ({$value} !== null)", self::INDENT."<p>{{ {$value} }}</p>", '@endif'],
            'date' => $this->ifFilled($value, ["<time datetime=\"{{ {$value}->toAtomString() }}\">{{ {$value}?->isoFormat('".((bool) ($config['time'] ?? false) ? 'D MMMM YYYY, HH:mm' : 'D MMMM YYYY')."') }}</time>"]),
            'toggle' => ["@if ({$value})", self::INDENT.'{{-- '.$field->name.' is on --}}', '@endif'],
            'choice' => (bool) ($config['multiple'] ?? false) ? $this->loop($value, $field->name, ['<span>{{ $%s->label() }}</span>']) : $this->ifFilled($value, ["<p>{{ {$value} }}</p>"]),
            'page' => (bool) ($config['multiple'] ?? true) ? $this->loop($value, $field->name, ['<a href="{{ $%s->url }}">@field($%1$s->title)</a>']) : $this->ifFilled($value, ["<a href=\"{{ {$value}->url }}\">@field({$value}->title)</a>"]),
            'page_table' => $this->loop($value, $field->name, ['<a href="{{ $%s->url }}">@field($%1$s->title)</a>']),
            'repeater' => $this->items($value, $field, $config),
            'matrix' => ["{{-- Each block renders templates/fields/{$field->name}/{type}.blade.php --}}", "@matrix({$value})"],
            'component' => ["@lynxComponent({$owner}, '{$field->name}')"],
            'images' => Value::int($config['max_files'] ?? 0) === 1
                ? $this->ifFilled($value, ["<x-lynx-image :image=\"{$value}\" :width=\"1200\" />"])
                : $this->loop($value, $field->name, ['<x-lynx-image :image="$%s" :width="600" />']),
            'files' => $this->loop($value, $field->name, ['<a href="{{ $%s->url() }}">{{ $%1$s->description() ?: $%1$s->name() }}</a>']),
            'icon' => $this->ifFilled($value, ["<x-dynamic-component :component=\"'lucide-'.{$value}\" class=\"size-5\" />"]),
            'password' => ["{{-- {$field->name}: a password, never printed --}}"],
            default => ["{{-- {$value}: a {$field->type} field; see Field Types in the docs --}}"],
        };
    }

    /**
     * @param  list<string>  $body
     * @return list<string>
     */
    private function ifFilled(string $value, array $body): array
    {
        return ["@if ({$value})", ...$this->indented($body), '@endif'];
    }

    /**
     * A loop over the value; each line of $body has %s where the item's variable name goes.
     *
     * @param  list<string>  $body
     * @return list<string>
     */
    private function loop(string $value, string $name, array $body): array
    {
        $item = $this->itemName($name, $value);

        return ["@foreach ({$value} as \${$item})", ...$this->indented(array_map(fn (string $line): string => sprintf($line, $item), $body)), '@endforeach'];
    }

    /**
     * A repeater: each item's fields, read from the item.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function items(string $value, Field $field, array $config): array
    {
        $type = $field->fieldType();
        $item = $this->itemName($field->name, $value);
        $body = [];

        foreach ($type instanceof ItemFieldType ? $type->subfields($config, null) : [] as $subfield) {
            array_push($body, ...$this->subfield('$'.$item, $subfield));
        }

        return ["@foreach ({$value} as \${$item})", ...$this->indented($body), '@endforeach'];
    }

    /**
     * @return list<string>
     */
    private function subfield(string $owner, Subfield $subfield): array
    {
        return $this->lines($owner, $subfield->field, $subfield->config());
    }

    /** The loop variable: the field's name in the singular, never the same as what it loops over. */
    private function itemName(string $name, string $value): string
    {
        $item = Str::camel(Str::singular($name));

        return str_contains($value, '$'.$item.'->') ? $item.'Item' : $item;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function indented(array $lines): array
    {
        return array_map(fn (string $line): string => self::INDENT.$line, $lines);
    }
}
