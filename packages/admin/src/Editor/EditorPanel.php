<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FieldDisplay;

/**
 * What the panel beside the page editor's fields shows: whether the page is live and has a draft,
 * how many of its translatable fields each language has its own text for, and its latest
 * activity. Templates that lay out their own sidebar do without it (ADR-0063).
 *
 * @internal
 */
final class EditorPanel
{
    /** Activity entries the panel lists; the Settings tab has the rest. */
    public const int RECENT_ACTIVITY = 3;

    /**
     * @param  list<FieldView>  $fields  the page's fields as the editor shows them (FieldViews)
     * @return array{isPublished: bool, hasDraft: bool, languages: list<array{code: string, name: string, own: int, total: int}>, activity: list<array{when: string, at: string, who: string, what: array<string, mixed>}>}
     */
    public function for(Page $page, array $fields, bool $hasDraft): array
    {
        $names = new ActorNames;

        return [
            'isPublished' => ! $page->hasStatus(PageStatus::UNPUBLISHED),
            'hasDraft' => $hasDraft,
            'languages' => $this->languages($fields),
            'activity' => array_map(
                fn (ActivityEvent $event): array => ActivityEntry::from($event, $names),
                lynx()->pages->activity((int) $page->id, self::RECENT_ACTIVITY),
            ),
        ];
    }

    /**
     * Per language, the translatable fields with their own text out of all of them. None on a site
     * with one language, or for a template without translatable fields.
     *
     * @param  list<FieldView>  $fields
     * @return list<array{code: string, name: string, own: int, total: int}>
     */
    private function languages(array $fields): array
    {
        $translatable = array_values(array_filter(
            $fields,
            fn (FieldView $field): bool => $field->isTranslatable && $field->display !== FieldDisplay::Hidden,
        ));

        if ($translatable === [] || ! lynx()->languages->isMultilingual()) {
            return [];
        }

        return array_values(lynx()->languages->all()->map(function ($language) use ($translatable): array {
            $code = Value::string($language->code);
            $own = array_filter($translatable, fn (FieldView $field): bool => ($field->values[$code] ?? null)?->isOwn === true);

            return ['code' => $code, 'name' => Value::string($language->name), 'own' => count($own), 'total' => count($translatable)];
        })->all());
    }
}
