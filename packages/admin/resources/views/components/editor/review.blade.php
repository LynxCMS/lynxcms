@props(['entries'])
{{-- What a save would change (ChangeReview entries), with the actions in the slot. Reused for agent plans. --}}
<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="review-heading">
    <h2 id="review-heading" class="mb-1 text-base font-semibold">{{ __('lynx-admin::editor.review.title') }}</h2>
    <p class="text-muted-foreground text-sm mb-4">{{ trans_choice('lynx-admin::editor.review.intro', count($entries)) }}</p>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-muted-foreground">
                <tr class="border-b border-border">
                    <th scope="col" class="py-2 pe-4 font-medium">{{ __('lynx-admin::editor.review.field') }}</th>
                    <th scope="col" class="py-2 pe-4 font-medium">{{ __('lynx-admin::editor.review.before') }}</th>
                    <th scope="col" class="py-2 font-medium">{{ __('lynx-admin::editor.review.after') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border align-top">
                @foreach ($entries as $entry)
                    <tr>
                        <th scope="row" class="py-2 pe-4 font-medium">
                            {{ $entry->label }}
                            @if ($entry->language) <span class="block font-normal text-muted-foreground">{{ $entry->language }}</span> @endif
                        </th>
                        <td class="py-2 pe-4 text-muted-foreground">
                            @if ($entry->before === null || $entry->before === '' || $entry->before === []) <span class="text-muted-foreground text-sm">{{ __('lynx-admin::editor.review.empty') }}</span>
                            @else <x-lynx-admin::editor.value :type="$entry->type" :value="$entry->before" /> @endif
                        </td>
                        <td class="py-2">
                            @if ($entry->after === null || $entry->after === '' || $entry->after === []) <span class="text-muted-foreground text-sm">{{ __('lynx-admin::editor.review.empty') }}</span>
                            @else <x-lynx-admin::editor.value :type="$entry->type" :value="$entry->after" /> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap gap-3">{{ $slot }}</div>
</section>
