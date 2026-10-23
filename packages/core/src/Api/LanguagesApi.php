<?php

declare(strict_types=1);

namespace LynxCms\Core\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Languages\DeleteLanguage;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Actions\Languages\UpdateLanguage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\View\LanguageSwitcher;

/**
 * $lynx->languages: ProcessWire-style API for languages. Writes go through actions.
 *
 * @api
 */
final readonly class LanguagesApi
{
    public function __construct(
        private ActionRunner $runner,
        private LanguageRegistry $registry,
        private LanguageContext $context,
    ) {}

    /**
     * @param  array<string, string>|null  $providerCodes
     */
    public function add(
        string $code,
        string $name,
        ?string $locale = null,
        ?string $fallback = null,
        ?string $prefix = null,
        bool $active = true,
        ?array $providerCodes = null,
        bool $dryRun = false,
    ): Language|ActionResult {
        $input = array_filter([
            'code' => $code,
            'name' => $name,
            'locale' => $locale,
            'fallback' => $fallback,
            'prefix' => $prefix,
            'provider_codes' => $providerCodes,
        ], fn (mixed $v): bool => $v !== null) + ['active' => $active];

        return $this->runner->run(CreateLanguage::class, $input, $dryRun)->valueOrDryRun(Language::class);
    }

    /**
     * @param  array<string, mixed>  $changes  code, name, locale, fallback, prefix, active, provider_codes
     */
    public function update(Language|string $language, array $changes, bool $dryRun = false): Language|ActionResult
    {
        return $this->runner->run(UpdateLanguage::class, ['language' => $this->code($language)] + $changes, $dryRun)->valueOrDryRun(Language::class);
    }

    public function delete(Language|string $language, bool $dryRun = false): Language|ActionResult
    {
        return $this->runner->run(DeleteLanguage::class, ['language' => $this->code($language)], $dryRun)->valueOrDryRun(Language::class);
    }

    public function get(Language|int|string $language): Language
    {
        return $this->registry->get($language);
    }

    public function find(Language|int|string|null $language): ?Language
    {
        return $this->registry->find($language);
    }

    /**
     * @return Collection<int, Language>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    /**
     * Whether the site has more than one language. With one, translation, fallbacks and the
     * "default" language mean nothing yet, so the admin leaves them out.
     */
    public function isMultilingual(): bool
    {
        return $this->registry->all()->count() > 1;
    }

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        return $this->registry->active();
    }

    /**
     * Links to the page in each language it is published in, keeping the request's query string,
     * for a language switcher: @foreach (lynx()->languages->switcher($page) as $link).
     *
     * @return list<array{language: Language, url: string, isCurrent: bool}>
     */
    public function switcher(Page $page, ?Request $request = null): array
    {
        return app(LanguageSwitcher::class)->links($page, $request);
    }

    public function default(): Language
    {
        return $this->registry->default();
    }

    public function current(): Language
    {
        return $this->context->current();
    }

    public function setCurrent(Language|string $language, bool $remember = false): Language
    {
        return $this->runner->run(SetCurrentLanguage::class, ['language' => $this->code($language), 'remember' => $remember])->valueAs(Language::class);
    }

    /**
     * @return list<Language>
     */
    public function chain(Language|string|null $language = null): array
    {
        return $language === null ? $this->context->chain() : $this->registry->chain($language);
    }

    /**
     * @template T
     *
     * @param  Closure(Language): T  $callback
     * @return T
     */
    public function with(Language|string $language, Closure $callback): mixed
    {
        return $this->context->with($language, $callback);
    }

    private function code(Language|string $language): string
    {
        return $language instanceof Language ? $language->code : $language;
    }
}
