<?php

declare(strict_types=1);

namespace LynxCms\Admin\Fields;

use LynxCms\Admin\Livewire\FieldForm;

/**
 * The field form's tabs, as ProcessWire's field editor splits them: Basics (what the field holds,
 * its name, languages and help), Settings (its kind's own settings), Access (ADR-0151), Advanced
 * (loading, locking and deleting) and Preview. A repeater or matrix adds its builder and its limits.
 * Each error key maps to the tab that holds its input, so the tab bar can mark it.
 *
 * @internal
 */
final readonly class FieldFormTabs
{
    private const array BASICS_SETTINGS = ['description', 'notes'];

    private const array ADVANCED_SETTINGS = ['autoload', 'locked'];

    public function __construct(
        private bool $hasItemBuilder,
        private bool $isNew,
        private bool $hasSettings,
        private bool $hasAccess,
    ) {}

    /**
     * Tab keys in order; the first is open when the form loads.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $tabs = match (true) {
            ! $this->hasItemBuilder => ['field', 'settings', 'access', 'advanced', 'preview'],
            $this->isNew => ['field', 'builder', 'preview', 'limits', 'settings', 'access', 'advanced'],
            default => ['builder', 'preview', 'field', 'limits', 'settings', 'access', 'advanced'],
        };

        return array_values(array_filter($tabs, fn (string $tab): bool => match ($tab) {
            'settings' => $this->hasSettings,
            'access' => $this->hasAccess,
            default => true,
        }));
    }

    /** The tab holding the input an error key ("settings.max_length") belongs to. */
    public function forError(string $key): string
    {
        $parts = explode('.', $key);

        if ($parts[0] !== 'settings' || ! isset($parts[1])) {
            return 'field';
        }

        return match (true) {
            in_array($parts[1], ['types', 'fields'], true) => 'builder',
            $parts[1] === 'access' => 'access',
            in_array($parts[1], self::ADVANCED_SETTINGS, true) => 'advanced',
            in_array($parts[1], self::BASICS_SETTINGS, true) => 'field',
            $this->hasItemBuilder && in_array($parts[1], FieldForm::ITEM_DISPLAY_SETTINGS, true) => 'limits',
            $this->hasSettings => 'settings',
            default => 'field',
        };
    }
}
