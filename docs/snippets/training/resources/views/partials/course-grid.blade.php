<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($courses as $course)
        @include('partials.course-card', ['course' => $course])
    @empty
        <p class="atlas-muted">@string('courses.none', ['en' => 'No courses here yet.', 'pt' => 'Ainda não há cursos aqui.'], scope: 'site')</p>
    @endforelse
</div>
