<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;

/**
 * What each field type holds, in words for the people who set up a site: a name ("Paragraphs"),
 * a group, a Lucide icon and one sentence. The types' own descriptions are written for agents
 * ("Setting "types" lists…"), so the field form uses these instead. Modules name their types with
 * app(FieldKinds::class)->register('rating', 'Star rating', 'Choices', 'star', 'One to five stars.').
 * Labels and descriptions can be translation keys.
 *
 * @api
 */
final class FieldKinds
{
    /** The group of types nobody named. */
    public const string OTHER = 'Other';

    /** Groups in the order the field form lists them. */
    public const array GROUPS = ['Text', 'Numbers and dates', 'Choices', 'Pages and media', 'Structure', self::OTHER];

    /** The groups' words in the language files. */
    private const array GROUP_LABELS = [
        'Text' => 'text',
        'Numbers and dates' => 'numbers',
        'Choices' => 'choices',
        'Pages and media' => 'media',
        'Structure' => 'structure',
        self::OTHER => 'other',
    ];

    /** @var array<string, array{label: string, group: string, icon: string, description: string}> label and description: text or translation keys */
    private array $kinds = [
        'text' => ['label' => 'lynx-admin::kinds.text.label', 'group' => 'Text', 'icon' => 'type', 'description' => 'lynx-admin::kinds.text.description'],
        'textarea' => ['label' => 'lynx-admin::kinds.textarea.label', 'group' => 'Text', 'icon' => 'align-left', 'description' => 'lynx-admin::kinds.textarea.description'],
        'url' => ['label' => 'lynx-admin::kinds.url.label', 'group' => 'Text', 'icon' => 'link', 'description' => 'lynx-admin::kinds.url.description'],
        'email' => ['label' => 'lynx-admin::kinds.email.label', 'group' => 'Text', 'icon' => 'at-sign', 'description' => 'lynx-admin::kinds.email.description'],
        'integer' => ['label' => 'lynx-admin::kinds.integer.label', 'group' => 'Numbers and dates', 'icon' => 'hash', 'description' => 'lynx-admin::kinds.integer.description'],
        'decimal' => ['label' => 'lynx-admin::kinds.decimal.label', 'group' => 'Numbers and dates', 'icon' => 'hash', 'description' => 'lynx-admin::kinds.decimal.description'],
        'date' => ['label' => 'lynx-admin::kinds.date.label', 'group' => 'Numbers and dates', 'icon' => 'calendar', 'description' => 'lynx-admin::kinds.date.description'],
        'time' => ['label' => 'lynx-admin::kinds.time.label', 'group' => 'Numbers and dates', 'icon' => 'clock', 'description' => 'lynx-admin::kinds.time.description'],
        'choice' => ['label' => 'lynx-admin::kinds.choice.label', 'group' => 'Choices', 'icon' => 'list', 'description' => 'lynx-admin::kinds.choice.description'],
        'toggle' => ['label' => 'lynx-admin::kinds.toggle.label', 'group' => 'Choices', 'icon' => 'toggle-left', 'description' => 'lynx-admin::kinds.toggle.description'],
        'icon' => ['label' => 'lynx-admin::kinds.icon.label', 'group' => 'Choices', 'icon' => 'smile', 'description' => 'lynx-admin::kinds.icon.description'],
        'page' => ['label' => 'lynx-admin::kinds.page.label', 'group' => 'Pages and media', 'icon' => 'file-text', 'description' => 'lynx-admin::kinds.page.description'],
        'page_table' => ['label' => 'lynx-admin::kinds.page_table.label', 'group' => 'Pages and media', 'icon' => 'table', 'description' => 'lynx-admin::kinds.page_table.description'],
        'images' => ['label' => 'lynx-admin::kinds.images.label', 'group' => 'Pages and media', 'icon' => 'image', 'description' => 'lynx-admin::kinds.images.description'],
        'files' => ['label' => 'lynx-admin::kinds.files.label', 'group' => 'Pages and media', 'icon' => 'paperclip', 'description' => 'lynx-admin::kinds.files.description'],
        'repeater' => ['label' => 'lynx-admin::kinds.repeater.label', 'group' => 'Structure', 'icon' => 'rows-3', 'description' => 'lynx-admin::kinds.repeater.description'],
        'matrix' => ['label' => 'lynx-admin::kinds.matrix.label', 'group' => 'Structure', 'icon' => 'blocks', 'description' => 'lynx-admin::kinds.matrix.description'],
        'component' => ['label' => 'lynx-admin::kinds.component.label', 'group' => 'Structure', 'icon' => 'puzzle', 'description' => 'lynx-admin::kinds.component.description'],
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
        $kind = $this->kinds[$type] ?? ['label' => Str::headline($type), 'group' => self::OTHER, 'icon' => 'square', 'description' => ''];

        return ['label' => $this->words($kind['label']), 'description' => $this->words($kind['description'])] + $kind;
    }

    /** A group's name in the admin's language; groups modules add keep their own words. */
    public function groupLabel(string $group): string
    {
        return isset(self::GROUP_LABELS[$group]) ? $this->words('lynx-admin::kinds.groups.'.self::GROUP_LABELS[$group]) : $this->words($group);
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

    private function words(string $text): string
    {
        $words = $text === '' ? '' : __($text);

        return is_string($words) ? $words : $text;
    }

    /** Named groups first, in order; groups modules add come before Other. */
    private function rank(string $group): int
    {
        $index = array_search($group, self::GROUPS, true);

        return $index === false ? count(self::GROUPS) - 1 : ($group === self::OTHER ? count(self::GROUPS) : $index);
    }
}
