<form method="get" class="mt-8 flex flex-wrap items-end gap-3" role="search">
    <label class="grid gap-1 text-sm">
        @string('courses.search', ['en' => 'Search', 'pt' => 'Pesquisar'])
        <x-ui.input type="search" name="q" :value="$filters['q'] ?? ''" class="w-64 rounded-full" />
    </label>
    <nav class="flex flex-wrap gap-2" aria-label="@string('courses.levels', ['en' => 'Levels', 'pt' => 'Níveis'])">
        <a href="{{ $page->url }}{{ isset($filters['q']) ? '?q='.urlencode($filters['q']) : '' }}" @class(['atlas-chip', 'is-active' => ! isset($filters['level'])])>@string('courses.all', ['en' => 'All', 'pt' => 'Todos'])</a>
        @foreach ($levels as $level)
            <a href="?{{ http_build_query(array_filter(['level' => $level->value, 'q' => $filters['q'] ?? null])) }}"
               @class(['atlas-chip', 'is-active' => ($filters['level'] ?? null) === $level->value])
               @if (($filters['level'] ?? null) === $level->value) aria-current="true" @endif>{{ $level }}</a>
        @endforeach
    </nav>
    <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">@string('courses.go', ['en' => 'Search', 'pt' => 'Pesquisar'])</button>
</form>
