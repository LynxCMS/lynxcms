{{-- One activity event in plain words for editors, from its parts (ActivityDescription). --}}
@props(['what'])
@php
    $join = fn (array $items): string => count($items) < 2 ? implode('', $items) : implode(', ', array_slice($items, 0, -1)).' and '.end($items);
    $languages = fn (array $languages): string => $languages === [] ? '' : ' ('.$join(array_column($languages, 'name')).')';
    $fields = array_map(fn (array $field): string => $field['label'].$languages($field['languages']), $what['fields']);
    $more = $what['more'] > 0 ? [$what['more'].' more '.Str::plural('field', $what['more'])] : [];
    $statusWords = ['published' => 'Published', 'unpublished' => 'Unpublished', 'hidden' => 'Hid', 'shown' => 'Unhid', 'locked' => 'Locked', 'unlocked' => 'Unlocked'];
    $statuses = array_map(fn (string $status): string => $statusWords[$status] ?? $status, $what['statuses']);
    $statuses = array_map(fn (string $word, int $index): string => $index === 0 ? $word : lcfirst($word), $statuses, array_keys($statuses));
    $schedule = array_map(fn (array $entry): string => ($entry['action'] === 'publish' ? 'publishing' : 'unpublishing').($entry['at'] === null ? ' cleared' : ' on '.$entry['at']), $what['schedule']);
@endphp
<span data-activity-kind="{{ $what['kind'] }}">@switch($what['kind'])
    @case('created') Created the page @break
    @case('changed') Changed {{ $join([...$fields, ...$more]) }} @break
    @case('saved') Saved the page @break
    @case('draft_saved') Saved a draft @break
    @case('draft_published') Published the draft @break
    @case('draft_discarded') Discarded the draft @break
    @case('status') {{ $statuses === [] ? 'Changed the status' : $join($statuses).' the page' }} @break
    @case('moved') Moved {{ $what['target'] === null ? 'the page' : 'under '.$what['target'] }} @break
    @case('sorted') Changed its place among its siblings @break
    @case('trashed') Moved to the trash @break
    @case('restored') Restored from the trash @break
    @case('revision_restored') Restored an earlier version @break
    @case('template_switched') Changed the template @break
    @case('lock_released') Ended someone's editing session @break
    @case('translated') Translated{{ $what['languages'] === [] ? ' the page' : ' into '.$join(array_column($what['languages'], 'name')) }} @break
    @case('scheduled') Scheduled {{ $join($schedule) }}{{ $languages($what['languages']) }} @break
    @default {{ $what['summary'] }}@if ($what['more'] > 0) <span class="text-muted-foreground">(and {{ $what['more'] }} more {{ Str::plural('change', $what['more']) }})</span>@endif
@endswitch</span>
