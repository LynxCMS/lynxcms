<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * What an activity event did to a page, in parts the activity-summary component words for
 * editors: the kind of change, the fields and languages a save touched, where a page moved, which
 * statuses changed. The actions' own summaries are written for developers and agents ("Update page
 * alpha: title [pt]"); actions this class doesn't know keep theirs.
 *
 * @phpstan-type Parts array{kind: string, fields: list<array{name: string, label: string, languages: list<array{code: string, name: string}>}>, more: int, statuses: list<string>, target: ?string, schedule: list<array{action: string, at: ?string}>, languages: list<array{code: string, name: string}>, summary: string}
 *
 * @internal
 */
final class ActivityDescription
{
    /** Fields named before the rest are counted. */
    public const int FIELDS_SHOWN = 3;

    /** Actions that need no more than their kind. */
    private const array KINDS = [
        'pages.save_draft' => 'draft_saved',
        'pages.publish_draft' => 'draft_published',
        'pages.discard_draft' => 'draft_discarded',
        'pages.sort' => 'sorted',
        'pages.trash' => 'trashed',
        'pages.restore' => 'restored',
        'pages.restore_revision' => 'revision_restored',
        'pages.switch_template' => 'template_switched',
        'pages.unlock_edit' => 'lock_released',
    ];

    /** A status a change added, and the word for taking it away. */
    private const array STATUS_WORDS = ['hidden' => 'shown', 'unpublished' => 'published', 'locked' => 'unlocked'];

    /**
     * @return Parts
     */
    public function of(ActivityEvent $event): array
    {
        $change = $event->changes[0] ?? [];
        $data = is_array($change['data'] ?? null) ? $change['data'] : [];
        $parts = ['kind' => self::KINDS[$event->action] ?? 'other', 'fields' => [], 'more' => 0, 'statuses' => [], 'target' => null, 'schedule' => [], 'languages' => [], 'summary' => ''];

        return match ($event->action) {
            'pages.save' => [...$parts, ...$this->saved($change, $data)],
            'pages.translate' => [...$parts, 'kind' => 'translated', 'languages' => $this->translatedInto($event)],
            'pages.status' => [...$parts, 'kind' => 'status', 'statuses' => $this->statuses($data)],
            'pages.move' => [...$parts, 'kind' => 'moved', 'target' => $this->title(Value::int($data['to'] ?? 0))],
            'pages.schedule' => [...$parts, 'kind' => 'scheduled', 'schedule' => $this->schedule($data), 'languages' => $this->languages([Value::string($data['language'] ?? '')])],
            'pages.run_schedules' => $this->ranSchedule($event, $parts) ?? $this->byShape($event, $change, $data, $parts),
            default => isset(self::KINDS[$event->action]) ? $parts : $this->byShape($event, $change, $data, $parts),
        };
    }

    /**
     * A scheduled publish or unpublish that ran, from the change the scheduler records after it.
     * The default language is not named: there, the whole page went live or offline. Events from
     * before the scheduler recorded it return null.
     *
     * @param  Parts  $parts
     * @return Parts|null
     */
    private function ranSchedule(ActivityEvent $event, array $parts): ?array
    {
        foreach ($event->changes as $change) {
            $data = is_array($change['data'] ?? null) ? $change['data'] : [];

            if (isset($data['scheduled'])) {
                $code = Value::string($data['language'] ?? '');
                $isDefault = $code === Value::string(lynx()->languages->default()->code);

                return [...$parts, 'kind' => 'schedule_ran', 'schedule' => [['action' => Value::string($data['scheduled']), 'at' => null]], 'languages' => $isDefault ? [] : $this->languages([$code])];
            }
        }

        return null;
    }

    /**
     * An action this class doesn't name, worded by what its first change did: modules save pages
     * through pages.save (a menu item, a form) and the scheduler changes statuses, so their first
     * change carries the same data. Anything else keeps its own summary.
     *
     * @param  array<array-key, mixed>  $change
     * @param  array<array-key, mixed>  $data
     * @param  Parts  $parts
     * @return Parts
     */
    private function byShape(ActivityEvent $event, array $change, array $data, array $parts): array
    {
        $isPage = ($change['subject'] ?? null) === 'page';

        return match (true) {
            $isPage && array_key_exists('fields', $data) => [...$parts, ...$this->saved($change, $data)],
            $isPage && is_array($data['from'] ?? null) && is_array($data['to'] ?? null) => [...$parts, 'kind' => 'status', 'statuses' => $this->statuses($data)],
            default => [...$parts, 'summary' => Value::string($change['summary'] ?? '', $event->action), 'more' => max(0, count($event->changes) - 1)],
        };
    }

    /**
     * @param  array<array-key, mixed>  $change
     * @param  array<array-key, mixed>  $data
     * @return array{kind: string, fields: list<array{name: string, label: string, languages: list<array{code: string, name: string}>}>, more: int}
     */
    private function saved(array $change, array $data): array
    {
        if (($change['type'] ?? null) === 'created') {
            return ['kind' => 'created', 'fields' => [], 'more' => 0];
        }

        $fields = $this->fields(Value::strings($data['fields'] ?? []));

        return [
            'kind' => $fields === [] ? 'saved' : 'changed',
            'fields' => array_slice($fields, 0, self::FIELDS_SHOWN),
            'more' => max(0, count($fields) - self::FIELDS_SHOWN),
        ];
    }

    /**
     * Groups "title [default]", "title [pt]", "price" by field, in the order they were written.
     *
     * @param  list<string>  $written
     * @return list<array{name: string, label: string, languages: list<array{code: string, name: string}>}>
     */
    private function fields(array $written): array
    {
        $codes = [];

        foreach ($written as $entry) {
            preg_match('/^([^\s\[]+)(?: \[([^\]]+)\])?/', $entry, $match);
            $name = $match[1] ?? $entry;
            $codes[$name] ??= [];

            if (isset($match[2])) {
                $codes[$name][] = $match[2];
            }
        }

        $fields = [];

        foreach ($codes as $name => $languages) {
            $fields[] = ['name' => (string) $name, 'label' => $this->label((string) $name), 'languages' => $this->languages($languages)];
        }

        return $fields;
    }

    private function label(string $name): string
    {
        $field = lynx()->fields->find($name);

        return $field instanceof Field && $field->label !== '' ? $field->label : Str::headline($name);
    }

    /**
     * The languages by name, in the site's order; none on a site with one language, where
     * languages are never mentioned.
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, name: string}>
     */
    private function languages(array $codes): array
    {
        if (! lynx()->languages->isMultilingual()) {
            return [];
        }

        return array_values(lynx()->languages->all()
            ->filter(fn (Language $language): bool => in_array($language->code, $codes, true))
            ->map(fn (Language $language): array => ['code' => Value::string($language->code), 'name' => Value::string($language->name)])
            ->all());
    }

    /**
     * A translation saves the target language's fields; their languages are the targets.
     *
     * @return list<array{code: string, name: string}>
     */
    private function translatedInto(ActivityEvent $event): array
    {
        $codes = [];

        foreach ($event->changes as $change) {
            $data = is_array($change['data'] ?? null) ? $change['data'] : [];

            foreach ($this->fields(Value::strings($data['fields'] ?? [])) as $field) {
                $codes = [...$codes, ...array_column($field['languages'], 'code')];
            }
        }

        return $this->languages(array_values(array_unique($codes)));
    }

    /**
     * "unpublished", "published", "hidden", "shown", "locked", "unlocked": what the change did.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private function statuses(array $data): array
    {
        $from = Value::strings($data['from'] ?? []);
        $to = Value::strings($data['to'] ?? []);
        $statuses = [];

        foreach (self::STATUS_WORDS as $status => $undone) {
            $statuses = match (true) {
                in_array($status, $to, true) && ! in_array($status, $from, true) => [...$statuses, $status],
                in_array($status, $from, true) && ! in_array($status, $to, true) => [...$statuses, $undone],
                default => $statuses,
            };
        }

        return $statuses;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array{action: string, at: ?string}>
     */
    private function schedule(array $data): array
    {
        $schedule = [];

        foreach (is_array($data['schedule'] ?? null) ? $data['schedule'] : [] as $action => $at) {
            $schedule[] = ['action' => (string) $action, 'at' => is_string($at) ? now()->parse($at)->toDayDateTimeString() : null];
        }

        return $schedule;
    }

    private function title(int $id): ?string
    {
        $page = $id > 0 ? lynx()->pages->get($id) : null;

        return $page instanceof Page ? Value::string($page->getUnformatted('title'), $page->name()) : null;
    }
}
