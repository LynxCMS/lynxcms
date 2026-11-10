{{-- Below the SEO tab's fields (AdminSlots::editorTab): the saved page (Page $page) as a search result and a social card. --}}
<section class="grid gap-3" aria-labelledby="seo-editor-preview" data-seo-preview>
    <div>
        <h2 id="seo-editor-preview" class="text-base font-semibold">{{ __('lynx-seo::admin.preview.title') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __('lynx-seo::admin.preview.description') }}</p>
    </div>
    @include('lynx-seo::admin.partials.cards', ['seo' => app(\LynxCms\Seo\SeoResolver::class)->resolve($page)])
</section>
