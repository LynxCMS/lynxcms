{{-- One activity event in plain words for editors, from its parts (ActivityDescription). --}}
@props(['what'])
@php
    $t = fn (string $key, array $replace = []): string => __('lynx-admin::activity.'.$key, $replace);
    $join = fn (array $items): string => count($items) < 2 ? implode('', $items) : implode(', ', array_slice($items, 0, -1)).' '.$t('and').' '.end($items);
    $languages = fn (array $languages): string => $languages === [] ? '' : ' ('.$join(array_column($languages, 'name')).')';
    $languageNames = $join(array_column($what['languages'], 'name'));
    $fields = array_map(fn (array $field): string => $field['label'].$languages($field['languages']), $what['fields']);
    $more = $what['more'] > 0 ? [trans_choice('lynx-admin::activity.more_fields', $what['more'])] : [];
    $statusWords = ['published', 'unpublished', 'hidden', 'shown', 'locked', 'unlocked'];
    $statuses = array_map(fn (string $status): string => in_array($status, $statusWords, true) ? $t('status.'.$status) : $status, $what['statuses']);
    $statuses = array_map(fn (string $word, int $index): string => $index === 0 ? $word : lcfirst($word), $statuses, array_keys($statuses));
    $scheduleWords = ['publish', 'unpublish', 'publish_draft'];
    $scheduleWord = fn (string $action): string => in_array($action, $scheduleWords, true) ? $t('schedule.'.$action) : $action;
    $schedule = array_map(fn (array $entry): string => $entry['at'] === null ? $t('schedule.cleared', ['what' => $scheduleWord($entry['action'])]) : $t('schedule.on', ['what' => $scheduleWord($entry['action']), 'at' => $entry['at']]), $what['schedule']);
    $firstSchedule = $what['schedule'][0]['action'] ?? '';
    $ranKey = ($firstSchedule === 'unpublish' ? 'schedule_unpublished' : 'schedule_published').($what['languages'] === [] ? '' : '_in');
@endphp
<span data-activity-kind="{{ $what['kind'] }}">@switch($what['kind'])
    @case('created') {{ $t('created') }} @break
    @case('changed') {{ $t('changed', ['what' => $join([...$fields, ...$more])]) }} @break
    @case('saved') {{ $t('saved') }} @break
    @case('draft_saved') {{ $t('draft_saved') }} @break
    @case('draft_published') {{ $t('draft_published') }} @break
    @case('draft_discarded') {{ $t('draft_discarded') }} @break
    @case('status') {{ $statuses === [] ? $t('status_changed') : $t('status_list', ['statuses' => $join($statuses)]) }} @break
    @case('moved') {{ $what['target'] === null ? $t('moved') : $t('moved_under', ['page' => $what['target']]) }} @break
    @case('sorted') {{ $t('sorted') }} @break
    @case('trashed') {{ $t('trashed') }} @break
    @case('restored') {{ $t('restored') }} @break
    @case('revision_restored') {{ $t('revision_restored') }} @break
    @case('template_switched') {{ $t('template_switched') }} @break
    @case('lock_released') {{ $t('lock_released') }} @break
    @case('translated') {{ $what['languages'] === [] ? $t('translated') : $t('translated_into', ['languages' => $languageNames]) }} @break
    @case('schedule_ran') {{ $firstSchedule === 'publish_draft' ? $t('schedule_draft') : $t($ranKey, ['languages' => $languageNames]) }} @break
    @case('scheduled') {{ $t('scheduled', ['what' => $join($schedule)]) }}{{ $languages($what['languages']) }} @break
    @default {{ $what['summary'] }}@if ($what['more'] > 0) <span class="text-muted-foreground">{{ trans_choice('lynx-admin::activity.and_more', $what['more']) }}</span>@endif
@endswitch</span>
