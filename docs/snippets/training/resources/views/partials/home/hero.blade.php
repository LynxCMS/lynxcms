<section class="relative overflow-hidden px-4 pb-16 pt-10">
    <div class="atlas-blob" style="width:420px;height:420px;background:var(--atlas-lilac);top:-80px;left:-120px"></div>
    <div class="atlas-blob" style="width:360px;height:360px;background:var(--atlas-peach);top:40px;right:-100px"></div>

    <div class="relative mx-auto max-w-4xl text-center">
        <x-ui.badge class="mb-5 rounded-full atlas-badge">
            <x-lucide-sparkles class="size-3" aria-hidden="true" />
            @string('home.badge', ['en' => 'New dates for next season', 'pt' => 'Novas datas para a próxima temporada'])
        </x-ui.badge>
        <h1 class="atlas-hero-title">@field($page->title)</h1>
        <p class="atlas-lead mx-auto mt-4 max-w-2xl">@field($page->summary)</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <a href="{{ lynx()->pages->get('/courses/')?->url }}" class="atlas-pill-btn atlas-pill-btn-primary">
                @string('home.cta.courses', ['en' => 'See all courses', 'pt' => 'Ver todos os cursos'])
            </a>
            <a href="#faqs" class="atlas-pill-btn atlas-pill-btn-outline">
                @string('home.cta.questions', ['en' => 'Questions?', 'pt' => 'Dúvidas?'])
            </a>
        </div>
    </div>
</section>
