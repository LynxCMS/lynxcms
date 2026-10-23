{{-- A course as a clay card: level, title, summary, hours and price, and its next session. --}}
@php($next = $course->children('template=session, starts>=now, availability=scheduled, sort=starts, limit=1')->first())
<article class="atlas-clay relative flex flex-col overflow-hidden" data-level="{{ $course->level?->value }}">
    <div class="atlas-card-band" data-level="{{ $course->level?->value }}">
        @if ($course->level)
            <x-ui.badge class="rounded-full atlas-badge">{{ $course->level }}</x-ui.badge>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5">
        <h3 class="text-lg"><a href="{{ $course->url }}" class="after:absolute after:inset-0">@field($course->title)</a></h3>
        <p class="atlas-muted flex-1 text-sm">@field($course->summary)</p>
        <p class="flex items-center justify-between text-sm font-semibold">
            <span>{{ $course->hours }} h</span>
            <span>{{ Illuminate\Support\Number::currency((float) $course->price, 'EUR', lynx()->languages->current()->appLocale()) }}</span>
        </p>
        @if ($next)
            <p class="atlas-muted text-xs">
                @string('courses.next', ['en' => 'Next', 'pt' => 'Próxima'], scope: 'site'):
                {{ $next->starts->locale(lynx()->languages->current()->appLocale())->isoFormat('D MMM') }} · {{ $next->location?->title }}
            </p>
        @endif
    </div>
</article>
