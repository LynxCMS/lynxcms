<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;

/**
 * What each field type holds, in words for the people who set up a site: a name ("Paragraphs"),
 * a group, a Lucide icon and one sentence. The types' own descriptions are written for agents
 * ("Setting "types" lists…"), so the field form uses these instead. Modules name their types with
 * app(FieldKinds::class)->register('rating', 'Star rating', 'Choices', 'star', 'One to five stars.').
 *
 * @api
 */
final class FieldKinds
{
    /** The group of types nobody named. */
    public const string OTHER = 'Other';

    /** Groups in the order the field form lists them. */
    public const array GROUPS = ['Text', 'Numbers and dates', 'Choices', 'Pages and media', 'Structure', self::OTHER];

    /** @var array<string, array{label: string, group: string, icon: string, description: string}> */
    private array $kinds = [
        'text' => ['label' => 'Single line', 'group' => 'Text', 'icon' => 'type', 'description' => 'A title, a name, a short phrase.'],
        'textarea' => ['label' => 'Paragraphs', 'group' => 'Text', 'icon' => 'align-left', 'description' => 'Plain text, rich text or Markdown.'],
        'url' => ['label' => 'Web address', 'group' => 'Text', 'icon' => 'link', 'description' => 'A link to a page or another site.'],
        'email' => ['label' => 'Email address', 'group' => 'Text', 'icon' => 'at-sign', 'description' => 'Checked when the page is saved.'],
        'integer' => ['label' => 'Whole number', 'group' => 'Numbers and dates', 'icon' => 'hash', 'description' => 'A count, a duration, a price in cents.'],
        'decimal' => ['label' => 'Decimal number', 'group' => 'Numbers and dates', 'icon' => 'hash', 'description' => 'A price or a rating.'],
        'date' => ['label' => 'Date', 'group' => 'Numbers and dates', 'icon' => 'calendar', 'description' => 'With or without a time.'],
        'time' => ['label' => 'Time of day', 'group' => 'Numbers and dates', 'icon' => 'clock', 'description' => 'Such as 09:30.'],
        'choice' => ['label' => 'Choice', 'group' => 'Choices', 'icon' => 'list', 'description' => 'One or more options from a list you set.'],
        'toggle' => ['label' => 'On or off', 'group' => 'Choices', 'icon' => 'toggle-left', 'description' => 'Yes or no, such as "Featured".'],
        'icon' => ['label' => 'Icon', 'group' => 'Choices', 'icon' => 'smile', 'description' => 'One of the Lucide icons.'],
        'page' => ['label' => 'Pages', 'group' => 'Pages and media', 'icon' => 'file-text', 'description' => 'Links to other pages, such as related courses.'],
        'page_table' => ['label' => 'Table of pages', 'group' => 'Pages and media', 'icon' => 'table', 'description' => 'Pages made for this one, such as sessions.'],
        'images' => ['label' => 'Images', 'group' => 'Pages and media', 'icon' => 'image', 'description' => 'With focus points and descriptions.'],
        'files' => ['label' => 'Files', 'group' => 'Pages and media', 'icon' => 'paperclip', 'description' => 'PDFs and documents.'],
        'repeater' => ['label' => 'Repeater', 'group' => 'Structure', 'icon' => 'rows-3', 'description' => 'A list of items with the same fields.'],
        'matrix' => ['label' => 'Blocks', 'group' => 'Structure', 'icon' => 'blocks', 'description' => 'A page built from blocks of different kinds.'],
        'component' => ['label' => 'App component', 'group' => 'Structure', 'icon' => 'puzzle', 'description' => 'A part of the app, such as a booking form.'],
    ];

    public function register(string $type, string $label, string $group, string $icon, string $description): void
    {
        $this->kinds[$type] = ['label' => $label, 'group' => $group, 'icon' => $icon, 'description' => $description];
    }

    /**
     * @return array{label: string, group: string, icon: string, description: string}
     */
    public function for(string $type): array
    {
        return $this->kinds[$type] ?? ['label' => Str::headline($type), 'group' => self::OTHER, 'icon' => 'square', 'description' => ''];
    }

    /**
     * The types grouped as the field form lists them, groups in order.
     *
     * @param  list<string>  $types
     * @return array<string, array<string, array{label: string, group: string, icon: string, description: string}>>
     */
    public function grouped(array $types): array
    {
        $groups = [];

        foreach ($types as $type) {
            $kind = $this->for($type);
            $groups[$kind['group']][$type] = $kind;
        }

        uksort($groups, fn (string $a, string $b): int => $this->rank($a) <=> $this->rank($b));

        return $groups;
    }

    /** Named groups first, in order; groups modules add come before Other. */
    private function rank(string $group): int
    {
        $index = array_search($group, self::GROUPS, true);

        return $index === false ? count(self::GROUPS) - 1 : ($group === self::OTHER ? count(self::GROUPS) : $index);
    }
}
