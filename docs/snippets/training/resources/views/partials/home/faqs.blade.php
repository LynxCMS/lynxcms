@if ($page->faqs->count())
    <section class="atlas-section-peach px-4 py-16" id="faqs">
        <div class="mx-auto max-w-3xl">
            <h2 class="atlas-section-title mb-10 text-center">@string('home.faqs', ['en' => 'Questions', 'pt' => 'Perguntas frequentes'])</h2>
            <div class="atlas-clay p-6">
                <x-ui.accordion type="single" collapsible>
                    @foreach ($page->faqs as $faq)
                        <x-ui.accordion-item :value="'faq-'.$loop->index">
                            <x-ui.accordion-trigger>@field($faq->heading)</x-ui.accordion-trigger>
                            <x-ui.accordion-content>@field($faq->text)</x-ui.accordion-content>
                        </x-ui.accordion-item>
                    @endforeach
                </x-ui.accordion>
            </div>
        </div>
    </section>
@endif
